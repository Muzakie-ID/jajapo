<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Preorder;
use App\Models\Shop;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Semua pesanan toko aktif: daftar dengan filter dan pencarian, serta rincian per pesanan. */
class OrderController extends Controller
{
    /** Kelompok status di filter. "Semua" tidak memuat pesanan kedaluwarsa karena tidak pernah dibayar. */
    private const GROUPS = [
        'menunggu-bayar' => [OrderStatus::PendingPayment],
        'disiapkan' => [OrderStatus::Paid],
        'siap' => [OrderStatus::Ready],
        'diantar' => [OrderStatus::OutForDelivery],
        'selesai' => [OrderStatus::PickedUp, OrderStatus::Delivered],
        'tidak-diambil' => [OrderStatus::NotPickedUp],
        'batal' => [OrderStatus::Cancelled, OrderStatus::Refunded],
    ];

    public function index(Request $request): Response
    {
        $shop = $this->shop($request);
        $preorders = $shop->preorders()->where('status', '!=', Preorder::STATUS_DRAFT)->latest('opens_at')->get();
        $preorders->each->setRelation('shop', $shop);

        // Bawaannya PO yang sedang buka; "semua" untuk melihat seluruh PO.
        $poParam = $request->query('po');
        $po = match (true) {
            $poParam === 'semua' => null,
            $poParam !== null => $preorders->firstWhere('id', (int) $poParam),
            default => $shop->preorders()->open()->orderBy('closes_at')->first(),
        };
        $group = array_key_exists((string) $request->query('status'), self::GROUPS) ? $request->query('status') : null;
        $fulfillment = in_array($request->query('cara'), ['ambil', 'antar'], true) ? $request->query('cara') : null;
        $place = $po ? $this->place($po, (string) $request->query('tempat')) : null;
        $q = trim((string) $request->query('q'));

        $base = fn () => $shop->orders()
            ->where('status', '!=', OrderStatus::Expired->value)
            ->when($po, fn ($w) => $w->where('preorder_id', $po->id))
            ->when($fulfillment, fn ($w) => $w->where('fulfillment', $fulfillment === 'antar' ? 'delivery' : 'pickup'))
            ->when($place, fn ($w) => $w->where($place['column'], $place['id']))
            ->when($q !== '', fn ($w) => $this->search($w, $q));

        $counts = $base()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $countFor = fn (array $statuses) => (int) collect($statuses)->sum(fn (OrderStatus $s) => $counts[$s->value] ?? 0);

        $orders = $base()
            ->with('buyer', 'items', 'slot', 'deliveryZone', 'preorder')
            ->when($group, fn ($w) => $w->whereIn('status', array_map(fn ($s) => $s->value, self::GROUPS[$group])))
            ->orderByRaw('paid_at is null')
            ->latest('paid_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $o) => $this->row($o));

        return Inertia::render('Seller/Orders', [
            'shop' => DashboardController::shopPropsFor($shop),
            'orders' => $orders,
            'filters' => [
                'po' => $po?->id ? (string) $po->id : 'semua',
                'status' => $group,
                'cara' => $fulfillment,
                'tempat' => $place ? (string) $request->query('tempat') : null,
                'q' => $q,
            ],
            'counts' => ['semua' => (int) $counts->sum(), ...collect(self::GROUPS)->map(fn ($s) => $countFor($s))->all()],
            'preorders' => $preorders->map(fn ($p) => ['id' => (string) $p->id, 'title' => $p->title, 'open' => $p->isOpen()])->values(),
            'places' => $po ? $this->placeOptions($po->load('slots', 'deliveryZones')) : [],
        ]);
    }

    public function show(Request $request, string $code): Response|RedirectResponse
    {
        $shop = $this->shop($request);
        $code = strtoupper($code);
        $order = $shop->orders()
            ->with('buyer', 'items', 'slot', 'deliveryZone', 'preorder', 'courier', 'pickedUpBy', 'complaints')
            ->where('code', $code)
            ->first();

        if (! $order) {
            // Dibuka dari notifikasi toko lain milik pengguna yang sama: pindah ke toko itu dulu.
            $other = $request->user()->shops()
                ->where('shops.status', Shop::STATUS_ACTIVE)
                ->whereHas('orders', fn ($q) => $q->where('code', $code))
                ->first();
            abort_unless($other, 404);
            $request->session()->put('current_shop_id', $other->id);

            return redirect("/seller/pesanan/{$code}")->with('success', "Sekarang mengelola {$other->name}.");
        }
        $delivery = $order->isDelivery();
        $complaint = $order->complaints->sortByDesc('id')->first();

        return Inertia::render('Seller/Order', [
            'shop' => DashboardController::shopPropsFor($shop),
            'order' => [
                ...$this->row($order),
                'email' => $order->buyer->email,
                'place_note' => $delivery ? null : $order->slot?->place_note,
                'address_note' => $order->address_note,
                'note' => $order->note,
                'preorder_id' => $order->preorder_id,
                'items' => $order->items->map(fn (OrderItem $i) => [
                    'name' => $i->shortName(),
                    'qty' => (int) $i->qty,
                    'unit_price' => (int) $i->unit_price,
                    'total' => (int) $i->unit_price * $i->qty,
                    'contents' => $i->isBundle() ? collect($i->components)->map(fn ($c) => $c['qty'].' '.$c['name'])->implode(' + ') : null,
                ])->values(),
                'subtotal' => (int) $order->subtotal,
                'delivery_fee' => (int) $order->delivery_fee,
                'timeline' => $this->timeline($order),
                'complaint' => $complaint ? [
                    'status' => $complaint->status,
                    'reason' => $complaint->reasonLabel(),
                    'amount_requested' => $complaint->amount_requested,
                    'amount_approved' => $complaint->amount_approved,
                    'open' => $complaint->isActive(),
                ] : null,
                'can_mark_ready' => $order->status === OrderStatus::Paid && ! $delivery,
                'can_hand_over' => in_array($order->status, [OrderStatus::Paid, OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::NotPickedUp], true),
            ],
        ]);
    }

    /** Menandai pesanan sudah diterima pembeli tanpa scan, misalnya kalau QR pembeli tidak terbaca. */
    public function handOver(Request $request, string $code, OrderService $orders): RedirectResponse
    {
        $order = $this->shop($request)->orders()->where('code', strtoupper($code))->firstOrFail();

        try {
            $orders->markPickedUp($order, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "{$order->code} ditandai ".($order->isDelivery() ? 'terkirim.' : 'sudah diambil.'));
    }

    private function row(Order $o): array
    {
        $delivery = $o->isDelivery();

        return [
            'code' => $o->code,
            'buyer' => $o->buyer->name,
            'phone' => $o->buyer_phone,
            'whatsapp_href' => $o->buyer_phone ? 'https://wa.me/'.preg_replace('/^0/', '62', preg_replace('/\D/', '', $o->buyer_phone)) : null,
            'items' => $o->itemsLabel(),
            'is_delivery' => $delivery,
            'place' => $delivery ? 'Antar ke '.($o->deliveryZone?->name ?? 'alamat pembeli') : ($o->slot?->place ?? '-'),
            'schedule' => $o->scheduleLabel(),
            'amount' => $o->shopAmount(),
            'status' => $o->status->value,
            'paid' => $o->paid_at ? tanggal($o->paid_at, withTime: true) : null,
            'preorder' => $o->preorder->title,
        ];
    }

    /** Langkah yang sudah dilalui pesanan, urut waktu. */
    private function timeline(Order $o): array
    {
        $steps = [['label' => 'Dipesan', 'at' => $o->created_at]];
        if ($o->paid_at) {
            $steps[] = ['label' => 'Dibayar lunas', 'at' => $o->paid_at];
        }
        if ($o->ready_at) {
            $steps[] = ['label' => $o->isDelivery() ? 'Siap diantar' : 'Siap diambil', 'at' => $o->ready_at];
        }
        if ($o->out_for_delivery_at) {
            $steps[] = ['label' => 'Mulai diantar', 'at' => $o->out_for_delivery_at, 'by' => $o->courier?->name];
        }
        if ($o->picked_up_at) {
            $steps[] = ['label' => $o->isDelivery() ? 'Terkirim' : 'Diambil', 'at' => $o->picked_up_at, 'by' => $o->pickedUpBy?->name];
        }

        $done = collect($steps)->map(fn ($s) => [
            'label' => $s['label'],
            'time' => tanggal($s['at'], withTime: true),
            'by' => $s['by'] ?? null,
        ]);

        // Status akhir yang tidak punya cap waktu sendiri.
        $end = match ($o->status) {
            OrderStatus::NotPickedUp => 'Tidak diambil sampai jadwal berakhir',
            OrderStatus::Cancelled => 'Dibatalkan',
            OrderStatus::Refunded => 'Di-refund penuh',
            OrderStatus::Expired => 'Kedaluwarsa sebelum dibayar',
            default => null,
        };

        return $end ? [...$done, ['label' => $end, 'time' => null, 'by' => null]] : $done->all();
    }

    private function search(Builder $query, string $q): void
    {
        $term = '%'.mb_strtolower(str_replace(['%', '_'], ['\%', '\_'], $q)).'%';
        $digits = preg_replace('/\D/', '', $q);

        $query->where(function ($w) use ($term, $digits) {
            $w->whereRaw('lower(orders.code) like ?', [$term])
                ->orWhereHas('buyer', fn ($b) => $b->whereRaw('lower(users.name) like ?', [$term]));
            if (strlen($digits) >= 4) {
                $w->orWhere('buyer_phone', 'like', '%'.$digits.'%');
            }
        });
    }

    /** "slot:12" atau "zona:3", hanya milik PO yang dipilih. */
    private function place(Preorder $po, string $value): ?array
    {
        if (! preg_match('/^(slot|zona):(\d+)$/', $value, $m)) {
            return null;
        }
        $owned = $m[1] === 'slot'
            ? $po->slots()->whereKey($m[2])->exists()
            : $po->deliveryZones()->whereKey($m[2])->exists();

        return $owned ? ['column' => $m[1] === 'slot' ? 'pickup_slot_id' : 'delivery_zone_id', 'id' => (int) $m[2]] : null;
    }

    private function placeOptions(Preorder $po): array
    {
        return [
            ...$po->slots->map(fn ($s) => ['value' => "slot:{$s->id}", 'label' => $s->place.', '.jam($s->starts_at)])->all(),
            ...$po->deliveryZones->map(fn ($z) => ['value' => "zona:{$z->id}", 'label' => "Antar ke {$z->name}"])->all(),
        ];
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}
