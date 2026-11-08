<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Complaint;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupSlot;
use App\Models\Preorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Angka satu PO untuk laporan LPJ dan ekspor Excel. Semua nominal dalam rupiah.
 *
 * Pesanan dihitung kalau sudah dibayar: lunas, diambil, terkirim, tidak diambil,
 * atau di-refund (refund dicatat terpisah supaya pendapatan bersih tetap benar).
 */
class PreorderReport
{
    public function __construct(
        public readonly Preorder $po,
        public readonly Collection $orders,
        public readonly Collection $refunds,
    ) {}

    public static function for(Preorder $po): self
    {
        $po->loadMissing('shop', 'slots', 'deliveryZones', 'products.variants');

        $orders = $po->orders()
            ->with('buyer', 'items', 'slot', 'deliveryZone')
            ->whereNotNull('paid_at')
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Expired->value, OrderStatus::PendingPayment->value])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $refunds = Complaint::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->where('status', Complaint::STATUS_REFUNDED)
            ->get();

        return new self($po, $orders, $refunds);
    }

    /** @return array<string, int> */
    public function totals(): array
    {
        $menu = (int) $this->orders->sum('subtotal');
        $delivery = (int) $this->orders->sum('delivery_fee');
        $refund = (int) $this->refunds->sum('amount_approved');
        $done = [OrderStatus::PickedUp, OrderStatus::Delivered];

        return [
            'orders' => $this->orders->count(),
            'portions' => (int) $this->orders->sum(fn (Order $o) => $o->portions()),
            'buyers' => $this->orders->pluck('buyer_id')->unique()->count(),
            'menu' => $menu,
            'delivery' => $delivery,
            'gross' => $menu + $delivery,
            'refund' => $refund,
            'net' => $menu + $delivery - $refund,
            // Posisi dana PO ini di saldo toko, langsung dari buku besar.
            'available' => (int) LedgerEntry::query()->whereIn('order_id', $this->orders->pluck('id'))->where('bucket', LedgerEntry::AVAILABLE)->sum('amount'),
            'held' => (int) LedgerEntry::query()->whereIn('order_id', $this->orders->pluck('id'))->where('bucket', LedgerEntry::HELD)->sum('amount'),
            'picked_up' => $this->orders->filter(fn (Order $o) => in_array($o->status, $done, true))->count(),
            'not_picked_up' => $this->orders->where('status', OrderStatus::NotPickedUp)->count(),
            'waiting' => $this->orders->filter(fn (Order $o) => $o->isPaid())->count(),
            'refunded_orders' => $this->refunds->count(),
        ];
    }

    /** Porsi dan pendapatan per menu dan varian. */
    public function byVariant(): Collection
    {
        return $this->orders
            ->flatMap(fn (Order $o) => $o->items)
            ->groupBy(fn (OrderItem $i) => $i->name.'|'.$i->unit_price)
            ->map(fn (Collection $items) => [
                'name' => $items->first()->name,
                'price' => (int) $items->first()->unit_price,
                'qty' => (int) $items->sum('qty'),
                'total' => (int) $items->sum(fn (OrderItem $i) => $i->qty * $i->unit_price),
            ])
            ->sortByDesc('qty')
            ->values();
    }

    /** Porsi yang harus dibuat per menu dan varian; paket dipecah ke isinya. */
    public function production(): Collection
    {
        return $this->orders
            ->flatMap(fn (Order $o) => $o->items)
            ->flatMap(fn (OrderItem $i) => $i->productionLines())
            ->groupBy('name')
            ->map(fn (Collection $lines, string $name) => ['name' => $name, 'qty' => (int) $lines->sum('qty')])
            ->sortByDesc('qty')
            ->values();
    }

    /** Jumlah pesanan dan porsi per titik ambil dan area antar. */
    public function byPlace(): Collection
    {
        $pickup = $this->po->slots->map(function (PickupSlot $s) {
            $orders = $this->orders->where('pickup_slot_id', $s->id);

            return [
                'kind' => 'Ambil',
                'place' => $s->place.($s->place_note ? ' ('.$s->place_note.')' : ''),
                'time' => tanggal($s->starts_at).', '.$s->timeLabel(),
                'orders' => $orders->count(),
                'portions' => (int) $orders->sum(fn (Order $o) => $o->portions()),
            ];
        });

        $delivery = $this->po->deliveryZones->map(function ($z) {
            $orders = $this->orders->where('delivery_zone_id', $z->id);

            return [
                'kind' => 'Antar',
                'place' => $z->name.' (ongkir '.rupiah($z->fee).')',
                'time' => $this->po->deliveryLabel() ?? '-',
                'orders' => $orders->count(),
                'portions' => (int) $orders->sum(fn (Order $o) => $o->portions()),
            ];
        });

        return $pickup->concat($delivery)->values();
    }

    /** Pesanan lunas per hari bayar (WIB). */
    public function byDay(): Collection
    {
        return $this->orders
            ->groupBy(fn (Order $o) => $o->paid_at->copy()->setTimezone(config('jajapo.timezone'))->toDateString())
            ->map(fn (Collection $orders, string $date) => [
                'date' => tanggal(Carbon::parse($date, config('jajapo.timezone')), withYear: true),
                'orders' => $orders->count(),
                'amount' => (int) $orders->sum(fn (Order $o) => $o->shopAmount()),
            ])
            ->values();
    }

    /** Daftar ambil: siapa mengambil apa di titik mana, untuk dicetak saat pembagian. */
    public function pickList(): Collection
    {
        return $this->orders
            ->sortBy(fn (Order $o) => [$o->fulfillment, $o->slot?->starts_at?->timestamp ?? 0, $o->buyer->name])
            ->map(fn (Order $o) => [
                'code' => $o->code,
                'buyer' => $o->buyer->name,
                'phone' => $o->buyer_phone,
                'items' => $o->items->map(fn (OrderItem $i) => $i->labelWithContents())->implode(', '),
                'place' => $o->placeLabel(),
                'address' => $o->isDelivery() ? $o->address_note : $o->slot?->place_note,
                'status' => $o->status->label(),
            ])
            ->values();
    }

    /** Baris pesanan lengkap untuk ekspor. */
    public function orderRows(): Collection
    {
        $refundByOrder = $this->refunds->keyBy('order_id');

        return $this->orders->map(fn (Order $o) => [
            'code' => $o->code,
            'paid_at' => $o->paid_at->copy()->setTimezone(config('jajapo.timezone'))->format('Y-m-d H:i'),
            'buyer' => $o->buyer->name,
            'phone' => $o->buyer_phone,
            'items' => $o->itemsLabel(),
            'portions' => $o->portions(),
            'fulfillment' => $o->isDelivery() ? 'Antar' : 'Ambil',
            'place' => $o->isDelivery() ? $o->deliveryZone?->name : $o->slot?->place,
            'address' => $o->address_note,
            'subtotal' => $o->subtotal,
            'delivery_fee' => $o->delivery_fee,
            'refund' => (int) ($refundByOrder->get($o->id)?->amount_approved ?? 0),
            'status' => $o->status->label(),
            'note' => $o->note,
        ])->values();
    }

    public function period(): string
    {
        return tanggal($this->po->opens_at, withTime: true, withYear: true).' sampai '.tanggal($this->po->closes_at, withTime: true, withYear: true);
    }
}
