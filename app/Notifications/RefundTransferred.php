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
}
