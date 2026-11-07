<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    public function approve(Request $request, Shop $shop): RedirectResponse
    {
        abort_unless($shop->status === Shop::STATUS_PENDING, 422);

        DB::transaction(function () use ($request, $shop) {
            $shop->update(['status' => Shop::STATUS_ACTIVE, 'approved_at' => now()]);
            $shop->bankAccounts()->whereNull('verified_at')->update(['verified_at' => now()]);
            AuditLog::record($request->user(), 'shop.approved', $shop);
        });

        return back()->with('success', "{$shop->name} disetujui. Rekeningnya ikut terverifikasi.");
    }

    public function reject(Request $request, Shop $shop): RedirectResponse
    {
        abort_unless($shop->status === Shop::STATUS_PENDING, 422);
        $shop->update(['status' => Shop::STATUS_REJECTED]);
        AuditLog::record($request->user(), 'shop.rejected', $shop);

        return back()->with('success', "Pendaftaran {$shop->name} ditolak.");
    }
}
