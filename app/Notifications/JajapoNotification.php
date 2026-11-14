<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Dasar semua notifikasi Jajapo: tersimpan di aplikasi (lonceng), dikirim ke HP lewat
 * web push kalau pengguna mengaktifkannya, dan lewat email kalau $mail true.
 * Dikirim lewat antrean setelah transaksi database selesai.
 */
abstract class JajapoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Kirim juga lewat email. Matikan untuk notifikasi yang sering, misalnya pesanan baru. */
    protected bool $mail = false;

    /** Kirim juga ke HP lewat web push. */
    protected bool $push = true;

    public function __construct()
    {
        $this->afterCommit();
    }

    abstract public function title(): string;

    abstract public function body(): string;

    /** Halaman yang dibuka saat notifikasi diketuk, relatif terhadap aplikasi. */
    abstract public function url(): string;

    /** Jenis notifikasi untuk ikon di daftar: order, complaint, money, shop, reminder. */
    public function kind(): string
    {
        return 'order';
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->push && method_exists($notifiable, 'pushSubscriptions') && config('webpush.vapid.public_key') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }
        if ($this->mail && filled($notifiable->routeNotificationFor('mail', $this))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body(), 'url' => $this->url(), 'kind' => $this->kind()];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->tag(static::class.':'.$this->url())
            ->data(['url' => $this->url()]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting('Halo'.(isset($notifiable->name) ? ' '.strtok($notifiable->name, ' ') : '').',')
            ->line($this->mailIntro());

        // Rincian ditaruh sebelum tombol supaya terbaca tanpa menggulir.
        foreach ($this->mailLines() as $line) {
            $mail->line($line);
        }

        return $mail
            ->action($this->mailAction(), url($this->url()))
            ->salutation('Salam, Jajapo')
            ->markdown('notifications::email', [
                'preheader' => $this->mailPreheader(),
                'hero' => $this->mailHero(),
                'highlight' => $this->mailHighlight(),
                'details' => $this->mailDetails(),
            ]);
    }

    /** Label kecil dan judul besar di pita hijau bagian atas email. */
    protected function mailHero(): array
    {
        $eyebrow = [
            'order' => 'Pesanan',
            'reminder' => 'Pesanan',
            'complaint' => 'Komplain',
            'money' => 'Saldo dan pencairan',
            'shop' => 'Toko',
            'feedback' => 'Masukan pengguna',
        ][$this->kind()] ?? 'Jajapo';

        return ['eyebrow' => $eyebrow, 'title' => $this->title()];
    }

    /** Paragraf pembuka email. Bawaannya sama dengan teks di lonceng. */
    protected function mailIntro(): string
    {
        return $this->body();
    }

    /**
     * Rincian berupa pasangan label dan nilai, tampil seperti struk.
     *
     * @return array<string, string>
     */
    protected function mailDetails(): array
    {
        return [];
    }

    protected function mailAction(): string
    {
        return 'Buka Jajapo';
    }

    /** Cuplikan yang tampil di daftar inbox sebelum email dibuka. */
    protected function mailPreheader(): string
    {
        return $this->body();
    }

    /**
     * Angka atau fakta utama yang ditonjolkan di email.
     *
     * @return array{label: string, value: string, note?: string}|null
     */
    protected function mailHighlight(): ?array
    {
        return null;
    }

    /** Baris tambahan khusus email. */
    protected function mailLines(): array
    {
        return [];
    }
}
