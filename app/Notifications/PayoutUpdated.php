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

    private function done(): bool
    {
        return $this->payout->status === Payout::STATUS_DONE;
    }

    protected function mailHero(): array
    {
        $this->payout->loadMissing('shop');

        return [
            'eyebrow' => 'Pencairan · '.$this->payout->shop->name,
            'title' => $this->done() ? 'Pencairan sudah ditransfer' : 'Pencairan ditolak',
        ];
    }

    protected function mailIntro(): string
    {
        return $this->done()
            ? 'Pencairan saldo tokomu sudah kami transfer. Bukti transfernya bisa dilihat di halaman Saldo.'
            : 'Pengajuan pencairan tidak bisa diproses. Nominalnya sudah kembali ke saldo tersedia, dan kamu bisa mengajukan lagi.';
    }

    protected function mailHighlight(): ?array
    {
        return $this->done()
            ? ['label' => 'Jumlah ditransfer', 'value' => rupiah($this->payout->amount), 'note' => 'Biaya transfer ditanggung Jajapo.']
            : ['label' => 'Kembali ke saldo tersedia', 'value' => rupiah($this->payout->amount)];
    }

    protected function mailDetails(): array
    {
        $p = $this->payout->loadMissing('bankAccount');

        return array_filter([
            'Rekening tujuan' => $p->bankAccount->label(),
            'Atas nama' => $p->bankAccount->account_name,
            'Diajukan' => tanggal($p->created_at, withTime: true),
            $this->done() ? 'Ditransfer' : 'Ditolak' => $p->processed_at ? tanggal($p->processed_at, withTime: true) : null,
            'Alasan' => $this->done() ? null : $p->note,
        ], fn ($v) => filled($v));
    }

    protected function mailAction(): string
    {
        return 'Lihat saldo';
    }

    protected function mailPreheader(): string
    {
        return $this->title().'. '.$this->body();
    }
}
