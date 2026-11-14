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

    protected function mailHero(): array
    {
        return [
            'eyebrow' => 'Pendaftaran toko',
            'title' => $this->shop->isActive() ? "{$this->shop->name} sudah aktif" : "Pendaftaran {$this->shop->name} ditolak",
        ];
    }

    protected function mailIntro(): string
    {
        return $this->shop->isActive()
            ? 'Tokomu sudah disetujui dan panel penjual sudah terbuka. Buat PO pertamamu, lalu bagikan tautannya ke pembeli.'
            : 'Pendaftaran tokomu belum bisa disetujui. Hubungi super admin Jajapo kalau ada data yang perlu diperbaiki.';
    }

    protected function mailDetails(): array
    {
        $this->shop->loadMissing('bankAccount');
        $account = $this->shop->bankAccount;

        return array_filter([
            'Toko' => $this->shop->name,
            'URL toko' => $this->shop->addressLabel(),
            'Rekening pencairan' => $account ? "{$account->label()} a.n. {$account->account_name}" : null,
            'Didaftarkan' => tanggal($this->shop->created_at, withTime: true),
            'Disetujui' => $this->shop->approved_at ? tanggal($this->shop->approved_at, withTime: true) : null,
        ], fn ($v) => filled($v));
    }

    protected function mailAction(): string
    {
        return $this->shop->isActive() ? 'Buat PO pertama' : 'Buka Jajapo';
    }
}
