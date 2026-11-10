<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Cari pesanan, toko, atau orang dari satu kotak. Dipakai saat menangani keluhan lewat WhatsApp. */
class SearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $digits = preg_replace('/\D/', '', $q);

        $orders = collect();
        $shops = collect();
        $people = collect();

        if (mb_strlen($q) >= 2) {
            $orders = Order::query()
                ->with('buyer', 'shop', 'items', 'slot', 'deliveryZone')
                ->where(function ($w) use ($like, $digits) {
                    $w->where('code', 'like', $like)
                        ->orWhereHas('buyer', fn ($b) => $b->where('name', 'like', $like)->orWhere('email', 'like', $like));
                    if (mb_strlen((string) $digits) >= 6) {
                        $w->orWhere('buyer_phone', 'like', '%'.$digits.'%');
                    }
                })
                ->latest('id')->limit(20)->get()
                ->map(fn (Order $o) => [
                    'code' => $o->code,
                    'status' => $o->status->value,
                    'buyer' => $o->buyer->name,
                    'phone' => $o->buyer_phone,
                    'shop' => $o->shop->name,
                    'shop_slug' => $o->shop->slug,
                    'items' => $o->itemsLabel(),
                    'total' => $o->total,
                    'date' => tanggal($o->created_at, withTime: true),
                ]);

            $shops = Shop::query()
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('slug', 'like', $like))
                ->orderBy('name')->limit(20)->get()
                ->map(fn (Shop $s) => [
                    'slug' => $s->slug,
                    'name' => $s->name,
                    'initials' => $s->initials(),
                    'status' => $s->status,
                ]);

            $people = User::query()
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->orderBy('name')->limit(20)->get()
                ->map(fn (User $u) => [
                    'name' => $u->name,
                    'email' => $u->email,
                    'super_admin' => (bool) $u->is_super_admin,
                    'orders' => $u->orders()->count(),
                ]);
        }

        return Inertia::render('Admin/Search', [
            'q' => $q,
            'orders' => $orders->values(),
            'shops' => $shops->values(),
            'people' => $people->values(),
        ]);
    }
}
