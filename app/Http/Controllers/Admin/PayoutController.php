<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    /** Semua pengajuan pencairan, bukan hanya antrean yang menunggu. */
    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), [Payout::STATUS_REQUESTED, Payout::STATUS_DONE, Payout::STATUS_REJECTED], true)
            ? $request->query('status')
            : null;

        $payouts = Payout::query()
            ->with('shop', 'bankAccount', 'processedBy')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Payout $p) => [
                'id' => $p->id,
                'shop' => $p->shop->name,
                'shop_slug' => $p->shop->slug,
                'amount' => $p->amount,
                'status' => $p->status,
                'bank' => $p->bankAccount->label(),
                'owner' => $p->bankAccount->account_name,
                'date' => tanggal($p->created_at, withTime: true, withYear: true),
                'done' => $p->processed_at ? tanggal($p->processed_at, withTime: true, withYear: true) : null,
                'by' => $p->processedBy?->name,
                'proof_url' => $p->proofUrl(),
                'note' => $p->note,
            ]);

        return Inertia::render('Admin/Payouts', [
            'payouts' => $payouts,
            'status' => $status,
            'totals' => [
                'requested' => (int) Payout::query()->where('status', Payout::STATUS_REQUESTED)->sum('amount'),
                'done' => (int) Payout::query()->where('status', Payout::STATUS_DONE)->sum('amount'),
            ],
        ]);
    }

    public function transfer(Request $request, Payout $payout, PayoutService $payouts): RedirectResponse
    {
        $request->validate(['proof' => ['nullable', 'image', 'max:4096']]);
        $payouts->markTransferred($payout, $request->user(), $request->file('proof'));

        return back()->with('success', 'Pencairan '.$payout->shop->name.' '.rupiah($payout->amount).' ditandai sudah ditransfer.');
    }

    public function reject(Request $request, Payout $payout, PayoutService $payouts): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:200']], ['note.required' => 'Tulis alasan penolakan.']);
        $payouts->reject($payout, $request->user(), $data['note']);

        return back()->with('success', 'Pencairan ditolak. Saldo dikembalikan ke saldo tersedia toko.');
    }
}
