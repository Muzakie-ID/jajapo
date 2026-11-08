<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Preorder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewPaidOrder;
use App\Notifications\OrderPaid;
use App\Payments\PaymentGateway;
use App\Support\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderService
{
    public function __construct(
        private PaymentGateway $gateway,
        private Ledger $ledger,
    ) {}

    /**
     * Membuat pesanan, mengunci kuota, lalu meminta QR dan kode unik ke gateway.
     *
     * @param  array<int, array{variant_id:int, qty:int}>  $items
     */
    public function checkout(
        User $buyer,
        Preorder $preorder,
        array $items,
        string $fulfillment,
        ?int $slotId,
        ?string $addressNote = null,
        ?string $phone = null,
        ?string $note = null,
        ?int $zoneId = null,
    ): Order {
        $preorder->loadMissing('shop', 'products.variants', 'slots', 'deliveryZones');

        if (! $preorder->isOpen()) {
            throw ValidationException::withMessages(['items' => 'PO ini sudah tutup.']);
        }

        $hasPending = $buyer->orders()
            ->where('preorder_id', $preorder->id)
            ->where('status', OrderStatus::PendingPayment)
            ->exists();
        if ($hasPending) {
            throw ValidationException::withMessages(['items' => 'Selesaikan dulu pembayaran pesanan sebelumnya di PO ini.']);
        }

        $items = collect($items)->filter(fn ($i) => (int) $i['qty'] > 0)->values();
        $totalQty = (int) $items->sum('qty');
        $maxQty = Setting::int('max_qty_per_order');
        if ($totalQty === 0) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu porsi.']);
        }
        if ($totalQty > $maxQty) {
            throw ValidationException::withMessages(['items' => "Maksimal {$maxQty} porsi per pesanan."]);
        }

        $variants = ProductVariant::query()
            ->whereIn('id', $items->pluck('variant_id'))
            ->whereHas('product', fn ($q) => $q->where('preorder_id', $preorder->id))
            ->with('product')
            ->get()
            ->keyBy('id');
        if ($variants->count() !== $items->pluck('variant_id')->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'Ada varian yang tidak ada di PO ini.']);
        }

        $slot = null;
        $zone = null;
        $deliveryFee = 0;
        if ($fulfillment === 'delivery') {
            if (! $preorder->offersDelivery()) {
                throw ValidationException::withMessages(['items' => 'PO ini tidak menerima pengantaran.']);
            }
            $zone = $preorder->deliveryZones->firstWhere('id', $zoneId);
            if (! $zone) {
                throw ValidationException::withMessages(['zone_id' => 'Pilih area antar.']);
            }
            if (blank($addressNote)) {
                throw ValidationException::withMessages(['address_note' => 'Isi alamat antar.']);
            }
            $deliveryFee = (int) $zone->fee;
        } else {
            $slot = $preorder->slots->firstWhere('id', $slotId);
            if (! $slot) {
                throw ValidationException::withMessages(['items' => 'Pilih slot ambil.']);
            }
        }

        $order = DB::transaction(function () use ($buyer, $preorder, $items, $variants, $slot, $zone, $fulfillment, $deliveryFee, $addressNote, $phone, $note) {
            $products = Product::query()
                ->where('preorder_id', $preorder->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $qtyPerProduct = [];
            foreach ($items as $item) {
                $productId = $variants[$item['variant_id']]->product_id;
                $qtyPerProduct[$productId] = ($qtyPerProduct[$productId] ?? 0) + (int) $item['qty'];
            }

            foreach ($qtyPerProduct as $productId => $qty) {
                $product = $products[$productId];
                if ($product->quota !== null && $product->sold_count + $qty > $product->quota) {
                    $left = max(0, $product->quota - $product->sold_count);
                    throw ValidationException::withMessages(['items' => "Kuota {$product->name} tinggal {$left} porsi."]);
                }
            }
            if ($preorder->quota !== null && $products->sum('sold_count') + array_sum($qtyPerProduct) > $preorder->quota) {
                $left = max(0, $preorder->quota - $products->sum('sold_count'));
                throw ValidationException::withMessages(['items' => "Kuota PO tinggal {$left} porsi."]);
            }

            foreach ($qtyPerProduct as $productId => $qty) {
                $products[$productId]->increment('sold_count', $qty);
            }

            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $variants[$item['variant_id']]->price() * (int) $item['qty'];
            }

            $order = Order::create([
                'code' => 'TMP-'.Str::upper(Str::random(12)),
                'shop_id' => $preorder->shop_id,
                'preorder_id' => $preorder->id,
                'buyer_id' => $buyer->id,
                'fulfillment' => $fulfillment,
                'pickup_slot_id' => $slot?->id,
                'delivery_zone_id' => $zone?->id,
                'address_note' => $fulfillment === 'delivery' ? $addressNote : null,
                'note' => $note,
                'buyer_phone' => $phone ?: $buyer->phone,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'unique_code' => 0,
                'total' => $subtotal + $deliveryFee,
                'status' => OrderStatus::PendingPayment,
                'ticket_token' => Str::random(40),
                'expires_at' => now()->addMinutes(Setting::int('payment_ttl_minutes')),
            ]);
            $order->update(['code' => 'JJP-'.(1000 + $order->id)]);

            foreach ($items as $item) {
                $variant = $variants[$item['variant_id']];
                $order->items()->create([
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'name' => $variant->product->name.', '.$variant->name,
                    'qty' => (int) $item['qty'],
                    'unit_price' => $variant->price(),
                ]);
            }

            return $order;
        });

        if ($phone && ! $buyer->phone) {
            $buyer->update(['phone' => $phone]);
        }

        try {
            $charge = $this->gateway->createCharge($order, $order->shopAmount(), $order->expires_at);
        } catch (Throwable $e) {
            report($e);
            $this->releaseQuota($order);
            $order->update(['status' => OrderStatus::Cancelled]);
            throw ValidationException::withMessages(['items' => 'QR pembayaran belum bisa dibuat. Coba lagi sebentar.']);
        }

        $order->update([
            'unique_code' => $charge->uniqueCode,
            'total' => $charge->total,
            'expires_at' => $charge->expiresAt,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'amount' => $charge->total,
            'unique_code' => $charge->uniqueCode,
            'gateway' => $this->gateway->name(),
            'gateway_ref' => $charge->reference,
            'qr_string' => $charge->qrString,
            'status' => Payment::STATUS_PENDING,
            'expires_at' => $charge->expiresAt,
        ]);

        return $order->fresh(['items', 'payment']);
    }

    /**
     * Melunasi pesanan: dana toko masuk saldo tertahan, kode unik jadi pendapatan platform.
     * Aman dipanggil berulang (callback ganda tidak melunasi dua kali).
     */
    public function markPaid(Order $order, string $method = Payment::METHOD_AUTO, ?User $actor = null): bool
    {
        return DB::transaction(function () use ($order, $method, $actor) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $allowed = [OrderStatus::PendingPayment];
            if ($method === Payment::METHOD_MANUAL) {
                $allowed[] = OrderStatus::Expired;
            }
            if (! in_array($order->status, $allowed, true)) {
                return false;
            }

            if ($order->status === OrderStatus::Expired) {
                $this->reserveQuota($order);
            }

            $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
            $order->payment?->update([
                'status' => Payment::STATUS_PAID,
                'method' => $method,
                'confirmed_by' => $actor?->id,
                'paid_at' => now(),
            ]);

            $shop = $order->shop;
            $this->ledger->record(LedgerEntry::GATEWAY_CLEARING, $order->total, 'order_paid', "Pembayaran {$order->code} masuk", order: $order);
            $this->ledger->record(LedgerEntry::HELD, $order->shopAmount(), 'order_paid', "Pesanan {$order->code} lunas", $shop, $order);
            if ($order->unique_code > 0) {
                $this->ledger->record(LedgerEntry::PLATFORM_REVENUE, $order->unique_code, 'unique_code', "Kode unik {$order->code}", order: $order);
            }

            if ($method === Payment::METHOD_MANUAL) {
                AuditLog::record($actor, 'order.mark_paid_manual', $order, ['total' => $order->total]);
            }

            $order->buyer->notify(new OrderPaid($order));
            Notify::shop($shop, new NewPaidOrder($order));

            return true;
        });
    }

    /** Scan tiket: pesanan diambil, dana pindah dari tertahan ke tersedia. */
    public function markPickedUp(Order $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! $order->isPaid() && $order->status !== OrderStatus::NotPickedUp) {
                throw ValidationException::withMessages(['token' => 'Tiket ini tidak bisa diambil: '.$order->status->label().'.']);
            }

            $order->update([
                'status' => $order->isDelivery() ? OrderStatus::Delivered : OrderStatus::PickedUp,
                'picked_up_at' => now(),
                'picked_up_by' => $actor->id,
            ]);
            $this->release($order, $order->isDelivery() ? 'terkirim' : 'diambil');
        });
    }

    /** Memindahkan dana pesanan dari saldo tertahan ke saldo tersedia, sekali saja. */
    public function release(Order $order, string $reason): void
    {
        if ($order->released_at) {
            return;
        }
        $amount = $order->shopAmount();
        $this->ledger->record(LedgerEntry::HELD, -$amount, 'order_released', "Pesanan {$order->code} {$reason}", $order->shop, $order);
        $this->ledger->record(LedgerEntry::AVAILABLE, $amount, 'order_released', "Pesanan {$order->code} {$reason}", $order->shop, $order);
        $order->update(['released_at' => now()]);
    }

    public function expire(Order $order): void
    {
        $changed = DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== OrderStatus::PendingPayment) {
                return false;
            }
            $order->update(['status' => OrderStatus::Expired]);
            $order->payment?->update(['status' => Payment::STATUS_EXPIRED]);
            $this->releaseQuota($order);

            return true;
        });

        if ($changed) {
            $this->cancelCharge($order);
        }
    }

    public function cancelByBuyer(Order $order): void
    {
        $changed = DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== OrderStatus::PendingPayment) {
                return false;
            }
            $order->update(['status' => OrderStatus::Cancelled]);
            $order->payment?->update(['status' => Payment::STATUS_CANCELLED]);
            $this->releaseQuota($order);

            return true;
        });

        if (! $changed) {
            throw ValidationException::withMessages(['order' => 'Pesanan ini sudah tidak bisa dibatalkan dari sini.']);
        }
        $this->cancelCharge($order);
    }

    private function cancelCharge(Order $order): void
    {
        $reference = $order->payment?->gateway_ref;
        if (! $reference) {
            return;
        }
        try {
            $this->gateway->cancel($reference);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function releaseQuota(Order $order): void
    {
        foreach ($order->items()->get() as $item) {
            Product::query()->whereKey($item->product_id)->where('sold_count', '>=', $item->qty)->decrement('sold_count', $item->qty);
        }
    }

    private function reserveQuota(Order $order): void
    {
        foreach ($order->items()->get() as $item) {
            $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
            if ($product->quota !== null && $product->sold_count + $item->qty > $product->quota) {
                throw ValidationException::withMessages(['order' => "Kuota {$product->name} sudah habis. Refund pembeli."]);
            }
            $product->increment('sold_count', $item->qty);
        }
    }
}
