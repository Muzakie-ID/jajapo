<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Preorder;
use App\Models\Shop;
use App\Services\Ledger;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');
        $soldStates = [...OrderStatus::paidStates(), OrderStatus::PickedUp->value, OrderStatus::Delivered->value, OrderStatus::NotPickedUp->value];

        $po = $shop->preorders()->open()->with('products', 'slots', 'deliveryZones')->orderBy('closes_at')->first()
            ?? $shop->preorders()->published()->with('products', 'slots', 'deliveryZones')->latest('closes_at')->first();

        $active = null;
        $kpis = null;
        $perDay = [];
        $rekap = [];
        if ($po) {
            $poOrders = $po->orders()->whereIn('status', $soldStates);
            $range = $request->query('rentang') === 'hari-ini' ? 'hari-ini' : 'semua';
            if ($range === 'hari-ini') {
                $poOrders->where('paid_at', '>=', now()->startOfDay());
            }
            $paid = (clone $poOrders)->get(['id', 'subtotal', 'delivery_fee', 'paid_at', 'status', 'fulfillment', 'pickup_slot_id', 'delivery_zone_id']);

            $kpis = [
                'range' => $range,
                'paid_count' => $paid->count(),
                'paid_today' => $paid->where('paid_at', '>=', now()->startOfDay())->count(),
                'revenue' => $paid->sum(fn ($o) => $o->subtotal + $o->delivery_fee),
                'sold' => $po->soldTotal(),
                'quota' => $po->quotaTotal(),
                'expired' => $po->orders()->where('status', OrderStatus::Expired)->count(),
                'waiting_pickup' => $paid->filter(fn ($o) => in_array($o->status->value, OrderStatus::paidStates(), true))->count(),
                'waiting_ready' => $paid->where('status', OrderStatus::Paid)->count(),
            ];

            $active = [
                'id' => $po->id,
                'title' => $po->title,
                'story_url' => $po->storyUrl(),
                'url' => $po->url(),
                'seed' => $po->id,
                'is_open' => $po->isOpen(),
                'closes_at' => $po->closes_at->toIso8601String(),
                'pickup_label' => ($s = $po->slots->first()) ? tanggal($s->starts_at) : ($po->delivery_starts_at ? tanggal($po->delivery_starts_at) : '-'),
                'slots' => $po->slots->map(fn ($s) => [
                    'id' => $s->id,
                    'place' => $s->place,
                    'time' => tanggal($s->starts_at).', '.$s->timeLabel(),
                    'count' => $paid->where('pickup_slot_id', $s->id)->count(),
                    'waiting' => $paid->where('pickup_slot_id', $s->id)->where('status', OrderStatus::Paid)->count(),
                ])->concat($po->delivery_enabled ? $po->deliveryZones->map(fn ($z) => [
                    'id' => null,
                    'place' => 'Antar ke '.$z->name,
                    'time' => ($po->deliveryLabel() ?? 'Jadwal antar belum diisi').', ongkir '.rupiah($z->fee),
                    'count' => $paid->where('delivery_zone_id', $z->id)->count(),
                    'waiting' => 0,
                ]) : [])->values(),
            ];

            $days = collect(range(4, 0))->map(fn ($i) => now()->startOfDay()->subDays($i));
            $allPaid = $po->orders()->whereIn('status', $soldStates)->whereNotNull('paid_at')->pluck('paid_at');
            $perDay = $days->map(fn (Carbon $day) => [
                'label' => $day->isToday() ? 'Hari ini' : ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][$day->dayOfWeek],
                'value' => $allPaid->filter(fn ($at) => Carbon::parse($at)->setTimezone(config('jajapo.timezone'))->isSameDay($day))->count(),
                'today' => $day->isToday(),
            ])->values();

            // Rekap produksi: paket dipecah ke menu isinya supaya dapur tahu jumlah tiap menu.
            $single = $po->products->reject->is_bundle->count() === 1;
            $rekap = OrderItem::query()
                ->whereHas('order', fn ($q) => $q->where('preorder_id', $po->id)->whereIn('status', $soldStates))
                ->get()
                ->flatMap(fn (OrderItem $i) => $i->productionLines())
                ->groupBy('name')
                ->map(fn ($lines, $name) => [
                    'name' => $single ? (new OrderItem(['name' => $name]))->shortName() : $name,
                    'qty' => (int) collect($lines)->sum('qty'),
                ])
                ->sortByDesc('qty')
                ->values();
        }

        $status = $request->query('status');
        $orders = $shop->orders()
            ->with('buyer', 'items', 'slot', 'deliveryZone')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Seller/Dashboard', [
            'shop' => $this->shopProps($shop),
            'active' => $active,
            'kpis' => $kpis,
            'perDay' => $perDay,
            'rekap' => $rekap,
            'orders' => $orders->map(fn (Order $o) => Present::orderRow($o)),
            'statusFilter' => $status,
            'balance' => $ledger->balances($shop),
            'draftCount' => $shop->preorders()->where('status', Preorder::STATUS_DRAFT)->count(),
        ]);
    }

    public function switchShop(Request $request): RedirectResponse
    {
        $data = $request->validate(['shop_id' => ['required', 'integer']]);
        $shop = $request->user()->shops()->where('shops.id', $data['shop_id'])->where('shops.status', Shop::STATUS_ACTIVE)->firstOrFail();
        $request->session()->put('current_shop_id', $shop->id);

        return redirect('/seller')->with('success', "Sekarang mengelola {$shop->name}.");
    }

    public static function shopPropsFor(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'slug' => $shop->slug,
            'initials' => $shop->initials(),
            'logo_url' => $shop->logoUrl(),
            'role' => $shop->pivot->role ?? request()->attributes->get('shopRole', 'member'),
        ];
    }

    private function shopProps(Shop $shop): array
    {
        return self::shopPropsFor($shop);
    }
}
