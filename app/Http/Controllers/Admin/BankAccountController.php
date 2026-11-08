<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Notifications\BankAccountReviewed;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankAccountController extends Controller
{
    /** Rekening baru jadi rekening pencairan; rekening lama dinonaktifkan. */
    public function approve(Request $request, BankAccount $account): RedirectResponse
    {
        abort_unless($account->isPendingChange(), 422);

        DB::transaction(function () use ($request, $account) {
            BankAccount::query()->where('shop_id', $account->shop_id)->where('is_active', true)->update(['is_active' => false]);
            $account->update(['is_active' => true, 'verified_at' => now()]);
            AuditLog::record($request->user(), 'bank_account.approved', $account, ['label' => $account->label()]);
        });

        Notify::shop($account->shop, new BankAccountReviewed($account->fresh()), adminsOnly: true);

        return back()->with('success', "Rekening {$account->label()} untuk {$account->shop->name} disetujui.");
    }

    public function reject(Request $request, BankAccount $account): RedirectResponse
    {
        abort_unless($account->isPendingChange(), 422);
        $data = $request->validate(['note' => ['required', 'string', 'max:200']], ['note.required' => 'Tulis alasan penolakan untuk penjual.']);

        $account->update(['rejected_at' => now(), 'review_note' => $data['note']]);
        AuditLog::record($request->user(), 'bank_account.rejected', $account, ['note' => $data['note']]);
        Notify::shop($account->shop, new BankAccountReviewed($account), adminsOnly: true);

        return back()->with('success', "Pengajuan rekening {$account->shop->name} ditolak.");
    }
}
