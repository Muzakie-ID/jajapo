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

    private function refunded(): bool
    {
        return $this->complaint->status === Complaint::STATUS_REFUNDED;
    }

    protected function mailHero(): array
    {
        $this->complaint->loadMissing('shop');

        return [
            'eyebrow' => ($this->forShop ? 'Keputusan super admin' : 'Komplain').' · '.$this->complaint->shop->name,
            'title' => ($this->refunded() ? 'Refund ' : 'Komplain ').$this->complaint->order->code.($this->refunded() ? ' disetujui' : ' ditolak'),
        ];
    }

    protected function mailIntro(): string
    {
        $code = $this->complaint->order->code;

        if ($this->forShop) {
            return $this->refunded()
                ? "Super admin menyetujui refund untuk pesanan {$code}. Nominal yang sebelumnya ditahan sudah dilepas, lalu refund dipotong dari saldo tersedia."
                : "Super admin menolak komplain untuk pesanan {$code}. Nominal yang ditahan selama komplain sudah kembali ke saldo tersedia.";
        }

        return $this->refunded()
            ? 'Komplainmu disetujui. Refund akan ditransfer paling lambat 1x24 jam hari kerja, dan kamu akan diberi tahu begitu sudah dikirim.'
            : 'Komplainmu tidak disetujui. Alasannya ada di rincian di bawah.';
    }

    protected function mailHighlight(): ?array
    {
        if (! $this->refunded()) {
            return null;
        }

        return $this->forShop
            ? ['label' => 'Dipotong dari saldo toko', 'value' => rupiah($this->complaint->amount_approved)]
            : ['label' => 'Refund disetujui', 'value' => rupiah($this->complaint->amount_approved), 'note' => "Ditransfer ke {$this->complaint->refund_to}."];
    }

    protected function mailDetails(): array
    {
        $c = $this->complaint->loadMissing('buyer', 'order');

        return array_filter([
            'Pesanan' => $c->order->code,
            'Pembeli' => $this->forShop ? $c->buyer->name : null,
            'Alasan komplain' => $c->reasonLabel(),
            'Refund diminta' => rupiah($c->amount_requested),
            'Refund disetujui' => $this->refunded() ? rupiah($c->amount_approved) : null,
            $this->refunded() ? 'Catatan' : 'Alasan ditolak' => $c->decision_note,
            'Diputuskan' => $c->decided_at ? tanggal($c->decided_at, withTime: true) : null,
        ], fn ($v) => filled($v));
    }

    protected function mailAction(): string
    {
        return $this->forShop ? 'Lihat komplain' : 'Lihat tiket';
    }
}
