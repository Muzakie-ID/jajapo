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
        return parent::toMail($notifiable)
            ->greeting('Halo,')
            ->line('Tautan berlaku sampai '.tanggal($this->invitation->expires_at, withTime: true).'.');
    }
}
