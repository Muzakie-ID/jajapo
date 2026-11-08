<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pengaturan toko, rekening, anggota, dan pencairan hanya untuk admin toko. Dipasang setelah shop-member. */
class EnsureShopAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->attributes->get('shopRole') === 'admin', 403, 'Bagian ini hanya untuk admin toko.');

        return $next($request);
    }
}
