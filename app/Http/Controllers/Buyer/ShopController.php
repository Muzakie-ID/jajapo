<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Preorder;
use App\Models\Shop;
use App\Support\Present;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(): Response
    {
        $shops = Shop::query()
            ->where('status', Shop::STATUS_ACTIVE)
            ->withCount(['preorders as open_count' => fn ($q) => $q->open()])
            ->orderByDesc('open_count')
            ->orderBy('name')
            ->get();

        return Inertia::render('Buyer/Shops', [
            'shops' => $shops->map(fn (Shop $s) => [
                'slug' => $s->slug,
                'name' => $s->name,
                'initials' => $s->initials(),
                'open_count' => $s->open_count,
            ]),
        ]);
    }

    public function show(Shop $shop): Response
    {
        abort_unless($shop->isActive(), 404);

        $preorders = $shop->preorders()->published()->with('shop', 'products.variants', 'slots')->latest('closes_at')->get();

        return Inertia::render('Buyer/Shop', [
            'shop' => [
                'name' => $shop->name,
                'slug' => $shop->slug,
                'initials' => $shop->initials(),
                'instagram' => $shop->instagram,
                'whatsapp' => $shop->whatsapp,
            ],
            'open' => $preorders->filter->isOpen()->map(fn ($p) => Present::preorderCard($p))->values(),
            'past' => $preorders->reject->isOpen()->take(10)->map(fn (Preorder $p) => [
                'title' => $p->title,
                'closed_label' => tanggal($p->closes_at, withYear: true),
                'sold' => $p->orders()->whereIn('status', [...OrderStatus::paidStates(), 'picked_up', 'delivered', 'not_picked_up'])->count(),
            ])->values(),
        ]);
    }
}
