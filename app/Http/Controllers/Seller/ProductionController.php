<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Preorder;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rekap produksi satu PO: berapa porsi tiap menu yang harus dibuat dan dibawa ke mana.
 * Paket dipecah ke menu isinya. Pesanan yang di-refund penuh tidak dihitung karena tidak perlu dibuat,
 * sama seperti rekap di beranda (Excel dan LPJ tetap menghitungnya untuk laporan uang).
 */
class ProductionController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');
        $preorders = $shop->preorders()->where('status', '!=', Preorder::STATUS_DRAFT)->latest('opens_at')->get();
        $preorders->each->setRelation('shop', $shop);

        // PO dari URL hanya kalau milik toko ini; selain itu sama seperti beranda: yang sedang buka, lalu yang terakhir tutup.
        $po = $preorders->firstWhere('id', (int) $request->query('po'))
            ?? $shop->preorders()->open()->orderBy('closes_at')->first()
            ?? $shop->preorders()->published()->latest('closes_at')->first();

        return Inertia::render('Seller/Production', [
            'shop' => DashboardController::shopPropsFor($shop),
            'preorders' => $preorders->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'open' => $p->isOpen()])->values(),
            'po' => $po ? $this->rekap($po->load('slots', 'deliveryZones', 'products')) : null,
        ]);
    }

    private function rekap(Preorder $po): array
    {
        $sold = [...OrderStatus::paidStates(), OrderStatus::PickedUp->value, OrderStatus::Delivered->value, OrderStatus::NotPickedUp->value];
        $orders = $po->orders()->with('items')->whereIn('status', $sold)->get();

        // Satu menu saja: nama varian cukup ("Original" alih-alih "Mac n Cheese, Original").
        $single = $po->products->reject->is_bundle->count() === 1;
        $lines = $orders->flatMap(fn (Order $o) => $o->items->flatMap(fn (OrderItem $i) => collect($i->productionLines())->map(fn ($l) => [
            'name' => $single ? (new OrderItem(['name' => $l['name']]))->shortName() : $l['name'],
            'qty' => $l['qty'],
            'status' => $o->status,
            'slot' => $o->pickup_slot_id,
            'zone' => $o->delivery_zone_id,
        ])));

        $done = [OrderStatus::PickedUp, OrderStatus::Delivered];
        $menus = $lines->groupBy('name')->map(fn (Collection $l, string $name) => [
            'name' => $name,
            'total' => (int) $l->sum('qty'),
            'waiting' => (int) $l->where('status', OrderStatus::Paid)->sum('qty'),
            'ready' => (int) $l->filter(fn ($x) => in_array($x['status'], [OrderStatus::Ready, OrderStatus::OutForDelivery], true))->sum('qty'),
            'handed' => (int) $l->filter(fn ($x) => in_array($x['status'], $done, true))->sum('qty'),
        ])->sortByDesc('total')->values();

        $place = fn (string $key, int $id, array $meta) => [
            ...$meta,
            'orders' => $orders->where($key === 'slot' ? 'pickup_slot_id' : 'delivery_zone_id', $id)->count(),
            'waiting_orders' => $orders->where($key === 'slot' ? 'pickup_slot_id' : 'delivery_zone_id', $id)->where('status', OrderStatus::Paid)->count(),
            'portions' => (int) $lines->where($key, $id)->sum('qty'),
            'menus' => $lines->where($key, $id)->groupBy('name')->map(fn ($l, $name) => ['name' => $name, 'qty' => (int) $l->sum('qty')])->sortByDesc('qty')->values(),
        ];
        $places = $po->slots->map(fn ($s) => $place('slot', $s->id, [
            'slot_id' => $s->id,
            'kind' => 'ambil',
            'place' => $s->place,
            'note' => $s->place_note,
            'time' => tanggal($s->starts_at).', '.$s->timeLabel(),
        ]))->concat($po->deliveryZones->map(fn ($z) => $place('zone', $z->id, [
            'slot_id' => null,
            'kind' => 'antar',
            'place' => "Antar ke {$z->name}",
            'note' => null,
            'time' => $po->deliveryLabel() ?? 'Jadwal antar belum diisi',
        ])))->filter(fn ($p) => $p['orders'] > 0 || $p['kind'] === 'ambil')->values();

        return [
            'id' => $po->id,
            'title' => $po->title,
            'open' => $po->isOpen(),
            'closes' => tanggal($po->closes_at, withTime: true),
            'summary' => [
                'portions' => (int) $lines->sum('qty'),
                'orders' => $orders->count(),
                'waiting_orders' => $orders->where('status', OrderStatus::Paid)->count(),
                'ready_orders' => $orders->filter(fn (Order $o) => in_array($o->status, [OrderStatus::Ready, OrderStatus::OutForDelivery], true))->count(),
                'handed_orders' => $orders->filter(fn (Order $o) => in_array($o->status, $done, true))->count(),
            ],
            'menus' => $menus,
            'places' => $places,
        ];
    }
}
