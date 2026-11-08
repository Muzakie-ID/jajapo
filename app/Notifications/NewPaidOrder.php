<?php

namespace App\Notifications;

use App\Models\Order;

/** Untuk anggota toko. Hanya di lonceng aplikasi supaya HP tidak berbunyi setiap ada pesanan. */
class NewPaidOrder extends JajapoNotification
{
    protected bool $push = false;

    public function __construct(public Order $order)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return "Pesanan baru {$this->order->code}";
    }

    public function body(): string
    {
        $this->order->loadMissing('buyer', 'items', 'slot', 'deliveryZone');

        return "{$this->order->buyer->name}, {$this->order->itemsLabel()}. {$this->order->placeLabel()}.";
    }

    public function url(): string
    {
        return '/seller#pesanan';
    }
}
