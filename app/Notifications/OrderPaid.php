<?php

namespace App\Notifications;

use App\Models\Order;

class OrderPaid extends JajapoNotification
{
    protected bool $mail = true;

    public function __construct(public Order $order)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return "Pembayaran {$this->order->code} diterima";
    }

    public function body(): string
    {
        $this->order->loadMissing('preorder', 'slot', 'deliveryZone', 'items');

        return "Tiket {$this->order->preorder->title} sudah siap ({$this->order->itemsLabel()}). {$this->order->slotLabel()}.";
    }

    public function url(): string
    {
        return "/pesanan/{$this->order->code}";
    }

    protected function mailLines(): array
    {
        return ['Tunjukkan QR di tiket saat mengambil pesanan atau saat kurir datang.'];
    }
}
