<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Complaint extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_REJECTED = 'rejected';

    /** Komplain yang masih berjalan menahan pencairan dana pesanannya. */
    public const ACTIVE = [self::STATUS_OPEN, self::STATUS_DISPUTED];

    public const REASONS = [
        'not_received' => 'Pesanan tidak diterima',
        'wrong_item' => 'Pesanan tidak sesuai',
        'missing_item' => 'Jumlah kurang',
        'bad_quality' => 'Basi, rusak, atau tumpah',
        'other' => 'Lainnya',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'seller_responded_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /** Penjual belum menanggapi lebih dari 24 jam: super admin boleh langsung memutuskan. */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->created_at->lte(now()->subDay());
    }
}
