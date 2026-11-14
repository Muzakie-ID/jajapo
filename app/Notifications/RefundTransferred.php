<?php

namespace App\Notifications;

use App\Models\Refund;

class RefundTransferred extends JajapoNotification
{
    protected bool $mail = true;

    public function __construct(public Refund $refund)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'money';
    }

    public function title(): string
    {
        return "Refund {$this->refund->order->code} sudah ditransfer";
    }

    public function body(): string
    {
        return rupiah($this->refund->amount).' sudah dikirim ke '.($this->refund->refund_to ?: 'rekeningmu').'. Cek mutasi rekening dalam 1x24 jam.';
    }

    public function url(): string
    {
        return "/pesanan/{$this->refund->order->code}";
    }

    protected function mailHero(): array
    {
        $this->refund->loadMissing('order.shop');

        return ['eyebrow' => 'Refund · '.$this->refund->order->shop->name, 'title' => 'Refund sudah ditransfer'];
    }

    protected function mailIntro(): string
    {
        return 'Refund dari komplainmu sudah kami transfer. Dana biasanya masuk dalam 1x24 jam, tergantung bank atau e-wallet tujuan.';
    }

    protected function mailHighlight(): ?array
    {
        return [
            'label' => 'Ditransfer ke '.($this->refund->refund_to ?: 'rekeningmu'),
            'value' => rupiah($this->refund->amount),
        ];
    }

    protected function mailDetails(): array
    {
        $r = $this->refund->loadMissing('order.shop');

        return array_filter([
            'Pesanan' => $r->order->code,
            'Toko' => $r->order->shop->name,
            'Ditransfer' => $r->transferred_at ? tanggal($r->transferred_at, withTime: true) : null,
        ], fn ($v) => filled($v));
    }

    protected function mailAction(): string
    {
        return 'Lihat tiket';
    }
}
