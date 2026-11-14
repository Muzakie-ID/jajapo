<?php

namespace App\Notifications;

use App\Models\ShopInvitation;
use Illuminate\Notifications\Messages\MailMessage;

/** Email undangan ke alamat yang diundang. Penerimanya bisa belum punya akun. */
class ShopInvited extends JajapoNotification
{
    public function __construct(public ShopInvitation $invitation)
    {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function kind(): string
    {
        return 'shop';
    }

    public function title(): string
    {
        return "Undangan bergabung ke {$this->invitation->shop->name}";
    }

    public function body(): string
    {
        $inviter = $this->invitation->inviter?->name ?? 'Admin toko';
        $role = mb_strtolower(ShopInvitation::ROLES[$this->invitation->role] ?? $this->invitation->role);

        return "{$inviter} mengundangmu sebagai {$role} {$this->invitation->shop->name} di Jajapo. Masuk dengan {$this->invitation->email}, lalu terima undangannya.";
    }

    public function url(): string
    {
        return '/undangan/'.$this->invitation->token;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return parent::toMail($notifiable)->greeting('Halo,');
    }

    protected function mailHero(): array
    {
        return ['eyebrow' => 'Undangan anggota toko', 'title' => "Bergabung ke {$this->invitation->shop->name}"];
    }

    protected function mailIntro(): string
    {
        $inviter = $this->invitation->inviter?->name ?? 'Admin toko';

        return "{$inviter} mengundangmu ikut mengelola toko ini di Jajapo. Masuk dengan email yang diundang, lalu terima undangannya.";
    }

    protected function mailDetails(): array
    {
        $i = $this->invitation;

        return array_filter([
            'Toko' => $i->shop->name,
            'Peran' => ShopInvitation::ROLES[$i->role] ?? $i->role,
            'Bisa' => $i->role === 'admin'
                ? 'Semua, termasuk rekening, anggota, dan pencairan'
                : 'Mengelola PO, scan tiket, dan menanggapi komplain',
            'Diundang oleh' => $i->inviter?->name,
            'Masuk dengan' => $i->email,
            'Berlaku sampai' => tanggal($i->expires_at, withTime: true),
        ], fn ($v) => filled($v));
    }

    protected function mailAction(): string
    {
        return 'Terima undangan';
    }

    protected function mailPreheader(): string
    {
        $inviter = $this->invitation->inviter?->name ?? 'Admin toko';

        return "{$inviter} mengundangmu bergabung ke {$this->invitation->shop->name}. Berlaku sampai ".tanggal($this->invitation->expires_at).'.';
    }
}
