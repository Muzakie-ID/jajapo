<?php

namespace App\Notifications;

use App\Models\Complaint;

/** Hasil komplain untuk pembeli, atau untuk penjual kalau yang memutuskan super admin. */
class ComplaintUpdated extends JajapoNotification
{
    protected bool $mail = true;

    public function __construct(public Complaint $complaint, public bool $forShop = false)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'complaint';
    }

    public function title(): string
    {
        $code = $this->complaint->order->code;

        if ($this->forShop) {
            return "Super admin memutuskan komplain {$code}";
        }

        return $this->complaint->status === Complaint::STATUS_REFUNDED ? "Refund {$code} disetujui" : "Komplain {$code} ditolak";
    }

    public function body(): string
    {
        $c = $this->complaint;
        $note = $c->decision_note ? " Catatan: {$c->decision_note}" : '';

        return $c->status === Complaint::STATUS_REFUNDED
            ? 'Refund '.rupiah($c->amount_approved).($this->forShop ? ' dipotong dari saldo toko.' : " akan ditransfer ke {$c->refund_to}.").$note
            : 'Komplain tidak disetujui.'.$note;
    }

    public function url(): string
    {
        return $this->forShop ? '/seller/komplain' : "/pesanan/{$this->complaint->order->code}";
    }
}
