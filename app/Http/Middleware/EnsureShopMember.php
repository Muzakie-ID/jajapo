<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menentukan toko yang sedang dikelola dan memastikan pengguna adalah anggotanya.
 * Semua query panel penjual diambil lewat toko ini, jadi data toko lain tidak ikut.
 */
class EnsureShopMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $shops = $user->shops()->where('shops.status', Shop::STATUS_ACTIVE)->get();

        if ($shops->isEmpty()) {
            $pending = $user->shops()->exists();

            return redirect('/seller/daftar')->with(
                'error',
                $pending ? 'Toko kamu masih menunggu persetujuan super admin.' : 'Daftarkan toko dulu untuk membuka panel penjual.',
            );
        }

        $currentId = (int) $request->session()->get('current_shop_id');
        $shop = $shops->firstWhere('id', $currentId) ?? $shops->first();
        $request->session()->put('current_shop_id', $shop->id);

        $request->attributes->set('shop', $shop);
        $request->attributes->set('shopRole', $shop->pivot->role ?? 'member');
        app()->instance(Shop::class, $shop);

        return $next($request);
    }
}
