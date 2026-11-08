<?php

namespace App\Notifications;

use App\Models\Shop;

class ShopReviewed extends JajapoNotification
{
    protected bool $mail = true;

    public function __construct(public Shop $shop)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'shop';
    }

    public function title(): string
    {
        return $this->shop->isActive() ? "{$this->shop->name} sudah aktif" : "Pendaftaran {$this->shop->name} ditolak";
    }

    public function body(): string
    {
        return $this->shop->isActive()
            ? 'Panel penjual sudah terbuka. Buat PO pertamamu dan bagikan ke pembeli.'
            : 'Hubungi super admin Jajapo kalau ada data yang perlu diperbaiki.';
    }

    public function url(): string
    {
        return $this->shop->isActive() ? '/seller/po/baru' : '/seller/daftar';
    }
}
