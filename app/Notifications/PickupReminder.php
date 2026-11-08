<?php

namespace App\Notifications;

use App\Models\Order;

class PickupReminder extends JajapoNotification
{
    public function __construct(public Order $order)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'reminder';
    }

    public function title(): string
    {
        $this->order->loadMissing('preorder');

        return $this->order->isDelivery()
            ? "{$this->order->preorder->title} diantar sebentar lagi"
            : "Waktunya ambil {$this->order->preorder->title}";
    }

    public function body(): string
    {
        $this->order->loadMissing('slot', 'deliveryZone', 'items');

        return $this->order->slotLabel().'. Siapkan tiket '.$this->order->code.'.';
    }

    public function url(): string
    {
        return "/pesanan/{$this->order->code}";
    }
}
