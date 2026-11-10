<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Shop;
use App\Notifications\ShopReviewed;
use App\Notifications\ShopSuspended;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    /** Semua toko, tidak hanya yang menunggu persetujuan. */
    public function index(Request $request): Response
    {
        $statuses = [Shop::STATUS_PENDING, Shop::STATUS_ACTIVE, Shop::STATUS_SUSPENDED, Shop::STATUS_REJECTED];
        $status = in_array($request->query('status'), $statuses, true) ? $request->query('status') : null;
        $term = '%'.mb_strtolower(trim((string) $request->query('q'))).'%';

        $shops = Shop::query()
            ->with('creator')
            ->withCount(['preorders', 'orders'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q) => $q->where(
                fn ($w) => $w->whereRaw('lower(name) like ?', [$term])->orWhereRaw('lower(slug) like ?', [$term])
            ))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Shop $s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'initials' => $s->initials(),
                'status' => $s->status,
                'owner' => $s->creator->name,
                'email' => $s->creator->email,
                'preorders' => $s->preorders_count,
                'orders' => $s->orders_count,
                'date' => tanggal($s->created_at, withYear: true),
            ]);

        return Inertia::render('Admin/Shops', [
            'shops' => $shops,
            'status' => $status,
            'q' => (string) $request->query('q'),
            'counts' => Shop::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approve(Request $request, Shop $shop): RedirectResponse
    {
        abort_unless($shop->status === Shop::STATUS_PENDING, 422);

        DB::transaction(function () use ($request, $shop) {
            $shop->update(['status' => Shop::STATUS_ACTIVE, 'approved_at' => now()]);
            $shop->bankAccounts()->where('is_active', true)->whereNull('verified_at')->update(['verified_at' => now()]);
            AuditLog::record($request->user(), 'shop.approved', $shop);
        });

        $shop->creator->notify(new ShopReviewed($shop->fresh()));

        return back()->with('success', "{$shop->name} disetujui. Rekeningnya ikut terverifikasi.");
    }

    public function reject(Request $request, Shop $shop): RedirectResponse
    {
        abort_unless($shop->status === Shop::STATUS_PENDING, 422);
        $shop->update(['status' => Shop::STATUS_REJECTED]);
        AuditLog::record($request->user(), 'shop.rejected', $shop);
        $shop->creator->notify(new ShopReviewed($shop));

        return back()->with('success', "Pendaftaran {$shop->name} ditolak.");
    }

    /** Bekukan toko aktif: panel penjual dan halaman tokonya ditutup, pesanan lama tetap jalan. */
    public function suspend(Request $request, Shop $shop): RedirectResponse
    {
        abort_unless($shop->status === Shop::STATUS_ACTIVE, 422);
        $data = $request->validate(
            ['reason' => ['required', 'string', 'max:200']],
            ['reason.required' => 'Isi alasan pembekuan.'],
        );

        $shop->update([
            'status' => Shop::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspend_reason' => $data['reason'],
        ]);
        AuditLog::record($request->user(), 'shop.suspended', $shop, ['reason' => $data['reason']]);
        $shop->creator->notify(new ShopSuspended($shop->fresh()));

        return back()->with('success', "{$shop->name} dibekukan. PO-nya tidak lagi tampil untuk pembeli.");
    }

    public function reactivate(Request $request, Shop $shop): RedirectResponse
    {
        abort_unless($shop->status === Shop::STATUS_SUSPENDED, 422);

        $shop->update(['status' => Shop::STATUS_ACTIVE, 'suspended_at' => null, 'suspend_reason' => null]);
        AuditLog::record($request->user(), 'shop.reactivated', $shop);
        $shop->creator->notify(new ShopSuspended($shop->fresh()));

        return back()->with('success', "{$shop->name} aktif kembali.");
    }
}
