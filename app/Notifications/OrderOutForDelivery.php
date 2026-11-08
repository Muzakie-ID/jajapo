<?php

namespace App\Notifications;

use App\Models\Order;

class OrderOutForDelivery extends JajapoNotification
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

        return "{$this->order->preorder->title} sedang diantar";
    }

    public function body(): string
    {
        $this->order->loadMissing('courier', 'deliveryZone');
        $courier = $this->order->courier?->name;

        return ($courier ? "{$courier} sedang menuju " : 'Kurir sedang menuju ').($this->order->deliveryZone?->name ?? 'alamatmu')
            .". Siapkan tiket {$this->order->code} untuk di-scan.";
    }

    public function url(): string
    {
        return "/pesanan/{$this->order->code}";
    }
}
