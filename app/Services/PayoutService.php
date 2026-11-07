<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    public function __construct(private Ledger $ledger) {}

    /** Penjual mengajukan pencairan; nominal langsung dikunci dari saldo tersedia. */
    public function request(Shop $shop, int $amount, User $user): Payout
    {
        $min = Setting::int('min_payout');
        if ($amount < $min) {
            throw ValidationException::withMessages(['amount' => 'Minimal pencairan '.rupiah($min).'.']);
        }

        return DB::transaction(function () use ($shop, $amount, $user) {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $account = $shop->bankAccount;
            if (! $account || ! $account->verified_at) {
                throw ValidationException::withMessages(['amount' => 'Rekening pencairan belum diverifikasi super admin.']);
            }

            $available = $this->ledger->balance($shop, LedgerEntry::AVAILABLE);
            if ($amount > $available) {
                throw ValidationException::withMessages(['amount' => 'Saldo tersedia hanya '.rupiah($available).'.']);
            }

            $payout = Payout::create([
                'shop_id' => $shop->id,
                'amount' => $amount,
                'bank_account_id' => $account->id,
                'status' => Payout::STATUS_REQUESTED,
                'requested_by' => $user->id,
            ]);

            $this->ledger->record(LedgerEntry::AVAILABLE, -$amount, 'payout_requested', 'Pengajuan pencairan ke '.$account->label(), $shop, payout: $payout);
            AuditLog::record($user, 'payout.requested', $payout, ['amount' => $amount]);

            return $payout;
        });
    }

    /** Super admin sudah mentransfer manual ke rekening toko. Biaya transfer ditanggung platform. */
    public function markTransferred(Payout $payout, User $admin, ?UploadedFile $proof = null): void
    {
        DB::transaction(function () use ($payout, $admin, $proof) {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            if ($payout->status !== Payout::STATUS_REQUESTED) {
                throw ValidationException::withMessages(['payout' => 'Pencairan ini sudah diproses.']);
            }

            $payout->update([
                'status' => Payout::STATUS_DONE,
                'proof_path' => $proof?->store('payout-proofs', 'public'),
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);
            $this->ledger->record(LedgerEntry::GATEWAY_CLEARING, -$payout->amount, 'payout_transferred', "Transfer pencairan {$payout->shop->name}", payout: $payout);
            AuditLog::record($admin, 'payout.transferred', $payout, ['amount' => $payout->amount]);
        });
    }

    public function reject(Payout $payout, User $admin, string $note): void
    {
        DB::transaction(function () use ($payout, $admin, $note) {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            if ($payout->status !== Payout::STATUS_REQUESTED) {
                throw ValidationException::withMessages(['payout' => 'Pencairan ini sudah diproses.']);
            }

            $payout->update([
                'status' => Payout::STATUS_REJECTED,
                'note' => $note,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);
            $this->ledger->record(LedgerEntry::AVAILABLE, $payout->amount, 'payout_rejected', 'Pencairan ditolak: '.$note, $payout->shop, payout: $payout);
            AuditLog::record($admin, 'payout.rejected', $payout, ['note' => $note]);
        });
    }
}
