<?php

namespace App\Notifications;

use App\Models\Order;

class OrderReady extends JajapoNotification
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
            ? "{$this->order->preorder->title} siap diantar"
            : "{$this->order->preorder->title} siap diambil";
    }

    public function body(): string
    {
        if ($this->order->isDelivery()) {
            return "Pesanan {$this->order->code} sudah selesai disiapkan dan menunggu kurir mengantar.";
        }

        $this->order->loadMissing('slot');
        $slot = $this->order->slot;
        $where = $slot ? "{$slot->place}, {$slot->timeLabel()}" : 'titik ambil';

        return "Pesanan {$this->order->code} sudah bisa diambil di {$where}. Tunjukkan tiket QR saat mengambil.";
    }

    public function url(): string
    {
        return "/pesanan/{$this->order->code}";
    }
}
