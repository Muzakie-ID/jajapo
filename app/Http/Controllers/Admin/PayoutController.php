<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
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
