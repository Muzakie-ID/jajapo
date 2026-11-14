<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Pesan obrolan baru. Hanya web push ke HP, tidak masuk lonceng: obrolan punya angka belum dibaca sendiri,
 * dan pesan beruntun dari percakapan yang sama digabung karena tag push-nya sama.
 */
class ChatMessageReceived extends JajapoNotification
{
    public function __construct(public Message $message, public string $recipientRole)
    {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        $subscribed = method_exists($notifiable, 'pushSubscriptions')
            && config('webpush.vapid.public_key')
            && $notifiable->pushSubscriptions()->exists();

        return $subscribed ? [WebPushChannel::class] : [];
    }

    public function kind(): string
    {
        return 'chat';
    }

    public function title(): string
    {
        $conversation = $this->message->conversation->loadMissing('shop', 'buyer');

        return $this->recipientRole === Conversation::BUYER
            ? $conversation->shop->name
            : "{$conversation->buyer->name} · {$conversation->shop->name}";
    }

    public function body(): string
    {
        return Str::limit($this->message->loadMissing('order')->preview(), 140);
    }

    public function url(): string
    {
        return "/obrolan/{$this->message->conversation_id}";
    }
}
