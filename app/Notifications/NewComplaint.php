<?php

namespace App\Notifications;

use App\Models\Complaint;

class NewComplaint extends JajapoNotification
{
    protected bool $mail = true;

    public function __construct(public Complaint $complaint)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'complaint';
    }

    public function title(): string
    {
        return "Komplain baru untuk {$this->complaint->order->code}";
    }

    public function body(): string
    {
        return $this->complaint->reasonLabel().', minta refund '.rupiah($this->complaint->amount_requested).'. Tanggapi dalam 24 jam.';
    }

    public function url(): string
    {
        return '/seller/komplain';
    }
}
