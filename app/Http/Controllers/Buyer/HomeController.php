<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\PickupPoint;
use App\Models\Preorder;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->only('titik', 'kategori', 'q');

        $query = Preorder::query()
            ->open()
            ->with('shop', 'products.variants', 'slots.point')
            ->when($filters['titik'] ?? null, fn ($q, $id) => $q->whereHas('slots', fn ($s) => $s->where('pickup_point_id', $id)))
            ->when($filters['kategori'] ?? null, function ($q, $cat) {
                $cat === 'antar' ? $q->where('delivery_enabled', true) : $q->where('category', $cat);
            })
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereHas('shop', fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', [$like])));
            })
            ->orderBy('closes_at');

        $preorders = $query->get();
        $closingSoon = $preorders->filter(fn (Preorder $p) => $p->closes_at->lte(now()->addHours(12)))->take(6);

        $pending = $request->user()?->orders()
            ->with('preorder', 'shop')
            ->where('status', OrderStatus::PendingPayment)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        return Inertia::render('Buyer/Home', [
            'points' => PickupPoint::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => (object) $filters,
            'closingSoon' => $closingSoon->map(fn ($p) => Present::preorderCard($p))->values(),
            'preorders' => $preorders->map(fn ($p) => Present::preorderCard($p))->values(),
            'pendingOrder' => $pending ? [
                'code' => $pending->code,
                'title' => $pending->preorder->title,
                'shop_name' => $pending->shop->name,
                'total' => $pending->total,
            ] : null,
        ]);
    }
}
