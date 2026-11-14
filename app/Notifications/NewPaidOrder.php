<?php

namespace App\Notifications;

use App\Models\Order;

/**
 * Untuk anggota toko: lonceng aplikasi saja supaya HP tidak berbunyi setiap ada pesanan.
 * Admin toko juga menerima email, karena merekalah yang memegang uangnya.
 */
class NewPaidOrder extends JajapoNotification
{
    protected bool $push = false;

    public function __construct(public Order $order)
    {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        $channels = parent::via($notifiable);

        $isShopAdmin = $this->order->shop->members()
            ->wherePivot('role', 'admin')
            ->whereKey($notifiable->getKey())
            ->exists();
        if ($isShopAdmin && filled($notifiable->routeNotificationFor('mail', $this))) {
            $channels[] = 'mail';
        }

        return $channels;
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

    protected function mailLines(): array
    {
        $this->order->loadMissing('shop', 'preorder');

        return [
            "PO: {$this->order->preorder->title}.",
            rupiah($this->order->shopAmount())." sudah masuk ke saldo tersedia {$this->order->shop->name} dan bisa dicairkan kapan saja.",
        ];
    }
}
