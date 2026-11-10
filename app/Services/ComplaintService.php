<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Notifications\ComplaintUpdated;
use App\Notifications\NewComplaint;
use App\Notifications\RefundTransferred;
use App\Support\Notify;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Komplain pembeli sampai refund.
 *
 * Uang toko untuk nominal yang diminta ditahan selama komplain berjalan. Kalau dana
 * pesanan sudah cair ke saldo tersedia, nominalnya dipindah kembali ke tertahan.
 * Refund dipotong dari bagian toko (subtotal + ongkir); kode unik tetap milik platform.
 */
class ComplaintService
{
    public function __construct(
        private Ledger $ledger,
        private OrderService $orders,
    ) {}

    /** Batas akhir komplain: H+1 setelah slot ambil atau jadwal antar terakhir PO. */
    public function deadline(Order $order): ?Carbon
    {
        $end = $order->preorder->serviceEndsAt() ?? $order->paid_at?->copy()->addDays(2);

        return $end?->copy()->addDay();
    }

    public function canComplain(Order $order): bool
    {
        $eligible = [...OrderStatus::paidStates(), OrderStatus::PickedUp->value, OrderStatus::Delivered->value, OrderStatus::NotPickedUp->value];

        return in_array($order->status->value, $eligible, true)
            && $order->complaints()->doesntExist()
            && ($deadline = $this->deadline($order)) !== null
            && $deadline->isFuture();
    }

    /** @param  array{reason:string, description:string, amount:int, refund_to:string}  $data */
    public function open(Order $order, User $buyer, array $data, ?UploadedFile $photo = null): Complaint
    {
        if ($order->buyer_id !== $buyer->id) {
            abort(404);
        }
        if (! $this->canComplain($order)) {
            throw ValidationException::withMessages(['reason' => 'Pesanan ini sudah tidak bisa dikomplain.']);
        }
        if ($data['amount'] > $order->shopAmount()) {
            throw ValidationException::withMessages(['amount' => 'Refund maksimal '.rupiah($order->shopAmount()).', sesuai harga pesanan dan ongkir.']);
        }

        $complaint = DB::transaction(function () use ($order, $buyer, $data) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            $complaint = Complaint::create([
                'order_id' => $order->id,
                'shop_id' => $order->shop_id,
                'buyer_id' => $buyer->id,
                'reason' => $data['reason'],
                'description' => $data['description'],
                'amount_requested' => $data['amount'],
                'refund_to' => $data['refund_to'],
                'status' => Complaint::STATUS_OPEN,
            ]);

            // Nominal yang diminta ditahan dari saldo tersedia selama komplain berjalan.
            $this->ledger->record(LedgerEntry::AVAILABLE, -$data['amount'], 'complaint_hold', "Ditahan karena komplain {$order->code}", $order->shop, $order);
            $this->ledger->record(LedgerEntry::HELD, $data['amount'], 'complaint_hold', "Ditahan karena komplain {$order->code}", $order->shop, $order);
            $complaint->update(['hold_amount' => $data['amount']]);

            AuditLog::record($buyer, 'complaint.opened', $complaint, ['order' => $order->code, 'amount' => $data['amount']]);
            Notify::shop($order->shop, new NewComplaint($complaint));

            return $complaint;
        });

        if ($photo) {
            $complaint->update(['photo_path' => $photo->store("complaints/{$complaint->id}", 'public')]);
        }

        return $complaint;
    }

    /** Penjual menolak: komplain naik ke super admin. Dana tetap ditahan. */
    public function sellerRejects(Complaint $complaint, User $seller, string $response): void
    {
        if ($complaint->status !== Complaint::STATUS_OPEN) {
            throw ValidationException::withMessages(['response' => 'Komplain ini sudah ditanggapi.']);
        }

        $complaint->update([
            'status' => Complaint::STATUS_DISPUTED,
            'seller_response' => $response,
            'seller_responded_at' => now(),
        ]);
        AuditLog::record($seller, 'complaint.seller_rejected', $complaint);
        Notify::superAdmins(new AdminAlert(
            "Komplain {$complaint->order->code} perlu keputusan",
            "{$complaint->shop->name} menolak refund ".rupiah($complaint->amount_requested).'. Alasan penjual: '.$response,
            '/admin/komplain',
            'complaint',
        ));
    }

    /**
     * Memutuskan komplain. Nominal 0 berarti ditolak. Penjual hanya boleh memutuskan
     * selama komplain masih menunggu tanggapannya; super admin boleh kapan saja selama berjalan.
     */
    public function resolve(Complaint $complaint, User $actor, int $amount, ?string $note = null, bool $asAdmin = false): Complaint
    {
        return DB::transaction(function () use ($complaint, $actor, $amount, $note, $asAdmin) {
            $complaint = Complaint::query()->lockForUpdate()->findOrFail($complaint->id);
            $order = Order::query()->lockForUpdate()->with('shop')->findOrFail($complaint->order_id);

            $allowed = $asAdmin ? $complaint->isActive() : $complaint->status === Complaint::STATUS_OPEN;
            if (! $allowed) {
                throw ValidationException::withMessages(['amount' => 'Komplain ini sudah diputuskan.']);
            }
            $max = $asAdmin ? $order->shopAmount() : $complaint->amount_requested;
            if ($amount < 0 || $amount > $max) {
                throw ValidationException::withMessages(['amount' => 'Refund maksimal '.rupiah($max).'.']);
            }

            // Dana yang ditahan selama komplain kembali ke saldo tersedia.
            if ($complaint->hold_amount > 0) {
                $this->ledger->record(LedgerEntry::HELD, -$complaint->hold_amount, 'complaint_release', "Komplain {$order->code} selesai", $order->shop, $order);
                $this->ledger->record(LedgerEntry::AVAILABLE, $complaint->hold_amount, 'complaint_release', "Komplain {$order->code} selesai", $order->shop, $order);
            }

            if ($amount > 0) {
                $this->ledger->record(LedgerEntry::AVAILABLE, -$amount, 'complaint_refund', "Refund komplain {$order->code}", $order->shop, $order);
                Refund::create([
                    'order_id' => $order->id,
                    'complaint_id' => $complaint->id,
                    'reason' => 'Komplain: '.$complaint->reasonLabel(),
                    'amount' => $amount,
                    'refund_to' => $complaint->refund_to,
                    'status' => Refund::STATUS_OPEN,
                ]);
                if ($amount >= $order->shopAmount()) {
                    $order->update(['status' => OrderStatus::Refunded]);
                }
            }

            $complaint->update([
                'status' => $amount > 0 ? Complaint::STATUS_REFUNDED : Complaint::STATUS_REJECTED,
                'amount_approved' => $amount,
                'decision_note' => $note,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'hold_amount' => 0,
                ...(! $asAdmin ? ['seller_responded_at' => now(), 'seller_response' => $note] : []),
            ]);
            AuditLog::record($actor, $asAdmin ? 'complaint.decided_by_admin' : 'complaint.approved_by_seller', $complaint, ['amount' => $amount]);
            $complaint->setRelation('order', $order);
            $complaint->buyer->notify(new ComplaintUpdated($complaint));
            if ($asAdmin) {
                Notify::shop($order->shop, new ComplaintUpdated($complaint, forShop: true));
            }
            if ($amount > 0) {
                Notify::superAdmins(new AdminAlert('Refund '.rupiah($amount)." untuk {$order->code} perlu ditransfer", "Transfer ke {$complaint->refund_to}.", '/admin/komplain', 'money'));
            }

            return $complaint;
        });
    }

    /** Super admin sudah mentransfer refund ke pembeli. */
    public function markRefundTransferred(Refund $refund, User $admin): void
    {
        DB::transaction(function () use ($refund, $admin) {
            $refund = Refund::query()->lockForUpdate()->with('order')->findOrFail($refund->id);
            if ($refund->status !== Refund::STATUS_OPEN) {
                return;
            }

            $refund->update(['status' => Refund::STATUS_DONE, 'transferred_at' => now(), 'resolved_by' => $admin->id]);
            // Refund komplain berasal dari uang yang tercatat di buku besar. Refund bayar telat
            // tidak pernah masuk buku besar, jadi tidak dicatat keluar.
            if ($refund->complaint_id) {
                $this->ledger->record(LedgerEntry::GATEWAY_CLEARING, -$refund->amount, 'refund_transferred', "Refund {$refund->order->code} ditransfer", order: $refund->order);
            }
            AuditLog::record($admin, 'refund.transferred', $refund, ['amount' => $refund->amount]);
            $refund->order->buyer->notify(new RefundTransferred($refund));
        });
    }
}
