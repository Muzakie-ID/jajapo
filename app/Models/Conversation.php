<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Obrolan antara satu pembeli dan satu toko. Semua anggota toko membaca dan membalas atas nama toko. */
class Conversation extends Model
{
    public const BUYER = 'buyer';

    public const SHOP = 'shop';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /** Pihak pengguna ini di percakapan: buyer, shop, atau null kalau bukan pesertanya. */
    public function roleOf(User $user): ?string
    {
        if ($this->buyer_id === $user->id) {
            return self::BUYER;
        }

        return $user->shops()->where('shops.id', $this->shop_id)->where('shops.status', Shop::STATUS_ACTIVE)->exists() ? self::SHOP : null;
    }

    public function markRead(string $role, int $messageId): void
    {
        $column = $role === self::BUYER ? 'buyer_read_id' : 'shop_read_id';
        if ($messageId > $this->{$column}) {
            $this->forceFill([$column => $messageId])->saveQuietly();
        }
    }

    /** Percakapan yang punya pesan belum dibaca oleh pihak $role. */
    public function scopeUnreadFor(Builder $query, string $role): Builder
    {
        $read = $role === self::BUYER ? 'buyer_read_id' : 'shop_read_id';
        $from = $role === self::BUYER ? self::SHOP : self::BUYER;

        return $query->whereHas('messages', fn ($m) => $m->where('sender_role', $from)->whereColumn('messages.id', '>', "conversations.{$read}"));
    }

    public function unreadCountFor(string $role): int
    {
        $read = $role === self::BUYER ? $this->buyer_read_id : $this->shop_read_id;

        return $this->messages()->where('sender_role', $role === self::BUYER ? self::SHOP : self::BUYER)->where('id', '>', $read)->count();
    }
}
