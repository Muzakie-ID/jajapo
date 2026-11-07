<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreorderController extends Controller
{
    public function show(Request $request, Shop $shop, string $preorder): Response
    {
        abort_unless($shop->isActive(), 404);
        $po = $shop->preorders()->published()->where('slug', $preorder)->with('shop', 'products.variants', 'slots', 'deliveryZones')->firstOrFail();

        $pending = $request->user()?->orders()
            ->where('preorder_id', $po->id)
            ->where('status', OrderStatus::PendingPayment)
            ->first();

        $firstSlot = $po->slots->first();

        return Inertia::render('Buyer/Preorder', [
            'preorder' => [
                'id' => $po->id,
                'url' => $po->url(),
                'title' => $po->title,
                'description' => $po->description,
                'labels' => $po->labels ?? [],
                'seed' => $po->id,
                'poster_url' => $po->posterUrl(),
                'shop' => ['name' => $shop->name, 'slug' => $shop->slug, 'verified' => true],
                'closes_at' => $po->closes_at->toIso8601String(),
                'is_open' => $po->isOpen(),
                'quota' => $po->quotaTotal(),
                'remaining' => $po->remaining(),
                'pickup_date_label' => match (true) {
                    $firstSlot !== null => tanggal($firstSlot->starts_at),
                    $po->delivery_starts_at !== null => tanggal($po->delivery_starts_at),
                    default => '-',
                },
                'products' => $po->products->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'variants' => $p->variants->map(fn ($v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                        'price' => $p->price + $v->price_delta,
                    ]),
                ]),
                'slots' => $po->slots->map(fn ($s) => Present::slot($s)),
                'delivery' => [
                    'enabled' => $po->offersDelivery(),
                    'schedule' => $po->deliveryLabel(),
                    'zones' => $po->deliveryZones->map(fn ($z) => ['id' => $z->id, 'name' => $z->name, 'fee' => $z->fee])->values(),
                ],
            ],
            'pendingOrder' => $pending ? ['code' => $pending->code] : null,
            'savedPhone' => $request->user()?->phone,
        ]);
    }
}
