<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $guarded = ['id'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Cuplikan untuk daftar obrolan dan notifikasi. */
    public function preview(): string
    {
        return match (true) {
            filled($this->body) => $this->body,
            filled($this->photo_path) => 'Foto',
            $this->order_id !== null => 'Pesanan '.($this->order?->code ?? ''),
            default => '',
        };
    }
}
