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

    protected function mailHero(): array
    {
        $this->account->loadMissing('shop');

        return [
            'eyebrow' => 'Rekening pencairan · '.$this->account->shop->name,
            'title' => $this->account->verified_at ? 'Rekening baru disetujui' : 'Ganti rekening ditolak',
        ];
    }

    protected function mailIntro(): string
    {
        return $this->account->verified_at
            ? 'Mulai sekarang, pencairan saldo tokomu dikirim ke rekening ini.'
            : 'Pencairan tetap dikirim ke rekening lama. Kamu bisa mengajukan rekening lain dari Pengaturan toko.';
    }

    protected function mailDetails(): array
    {
        $a = $this->account;
        $reviewedAt = $a->verified_at ?? $a->rejected_at;

        return array_filter([
            'Rekening' => $a->label(),
            'Atas nama' => $a->account_name,
            'Alasan' => $a->verified_at ? null : $a->review_note,
            $a->verified_at ? 'Disetujui' : 'Ditolak' => $reviewedAt ? tanggal($reviewedAt, withTime: true) : null,
        ], fn ($v) => filled($v));
    }

    protected function mailAction(): string
    {
        return $this->account->verified_at ? 'Lihat saldo' : 'Buka pengaturan';
    }
}
