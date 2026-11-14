<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Setting;

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
        $this->order->loadMissing('preorder');

        return ["PO: {$this->order->preorder->title}."];
    }

    protected function mailPreheader(): string
    {
        $this->order->loadMissing('shop');

        return rupiah($this->order->shopAmount())." masuk ke saldo {$this->order->shop->name}. ".$this->body();
    }

    protected function mailHighlight(): ?array
    {
        $this->order->loadMissing('shop');

        return [
            'label' => "Masuk ke saldo tersedia {$this->order->shop->name}",
            'value' => rupiah($this->order->shopAmount()),
            'note' => 'Bisa dicairkan kapan saja mulai '.rupiah(Setting::int('min_payout')).'.',
        ];
    }
}
