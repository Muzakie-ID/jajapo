<?php

namespace App\Notifications;

use App\Models\BankAccount;

class BankAccountReviewed extends JajapoNotification
{
    protected bool $mail = true;

    public function __construct(public BankAccount $account)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'money';
    }

    public function title(): string
    {
        return $this->account->verified_at ? 'Rekening baru disetujui' : 'Pengajuan rekening ditolak';
    }

    public function body(): string
    {
        return $this->account->verified_at
            ? "Pencairan berikutnya dikirim ke {$this->account->label()} a.n. {$this->account->account_name}."
            : "{$this->account->label()} tidak disetujui. Alasan: {$this->account->review_note}";
    }

    public function url(): string
    {
        return '/seller/pengaturan';
    }
}
