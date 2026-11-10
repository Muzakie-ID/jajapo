<?php

namespace App\Notifications;

use App\Models\Shop;

/** Toko dibekukan atau diaktifkan kembali oleh super admin. */
class ShopSuspended extends JajapoNotification
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
        return $this->shop->isActive()
            ? "{$this->shop->name} aktif kembali"
            : "{$this->shop->name} dibekukan";
    }

    public function body(): string
    {
        if ($this->shop->isActive()) {
            return 'Panel penjual dan halaman tokomu bisa dipakai lagi seperti biasa.';
        }

        return 'Panel penjual dan halaman tokomu ditutup sementara. Alasan: '
            .($this->shop->suspend_reason ?: 'tidak disebutkan')
            .'. Hubungi pengelola Jajapo untuk tindak lanjut.';
    }

    public function url(): string
    {
        return '/seller';
    }
}
