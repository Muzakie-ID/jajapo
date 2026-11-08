<?php

namespace App\Notifications;

use App\Models\Payout;

class PayoutUpdated extends JajapoNotification
{
    protected bool $mail = true;

    public function __construct(public Payout $payout)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'money';
    }

    public function title(): string
    {
        return $this->payout->status === Payout::STATUS_DONE
            ? 'Pencairan '.rupiah($this->payout->amount).' sudah ditransfer'
            : 'Pencairan '.rupiah($this->payout->amount).' ditolak';
    }

    public function body(): string
    {
        $this->payout->loadMissing('bankAccount');

        return $this->payout->status === Payout::STATUS_DONE
            ? 'Dikirim ke '.$this->payout->bankAccount->label().'.'
            : 'Saldo dikembalikan ke saldo tersedia.'.($this->payout->note ? " Alasan: {$this->payout->note}" : '');
    }

    public function url(): string
    {
        return '/seller/saldo';
    }
}
