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

    protected function mailHero(): array
    {
        $this->order->loadMissing('shop', 'preorder');

        return ['eyebrow' => "Pembayaran diterima · {$this->order->shop->name}", 'title' => $this->order->preorder->title];
    }

    protected function mailIntro(): string
    {
        return 'Pembayaranmu sudah kami terima dan tiketmu sudah terbit.';
    }

    protected function mailDetails(): array
    {
        $o = $this->order->loadMissing('items', 'slot', 'deliveryZone', 'preorder');
        $delivery = $o->isDelivery();

        return array_filter([
            'Kode pesanan' => $o->code,
            'Pesanan' => $o->itemsLabel(),
            $delivery ? 'Antar ke' : 'Ambil di' => $delivery
                ? $o->deliveryZone?->name
                : trim(($o->slot?->place ?? '').($o->slot?->place_note ? ', '.$o->slot->place_note : ''), ', '),
            'Jadwal' => $o->scheduleLabel(),
            'Total dibayar' => rupiah($o->total),
        ], fn ($v) => filled($v));
    }

    protected function mailLines(): array
    {
        return ['Tunjukkan QR di tiket saat mengambil pesanan atau saat kurir datang.'];
    }

    protected function mailAction(): string
    {
        return 'Lihat tiket';
    }
}
