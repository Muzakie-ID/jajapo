<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Preorder extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'labels' => 'array',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'delivery_enabled' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('sort');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(PickupSlot::class)->orderBy('starts_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** PO yang sedang buka dari toko aktif. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>', now())
            ->whereHas('shop', fn ($q) => $q->where('status', Shop::STATUS_ACTIVE));
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->opens_at->lte(now())
            && $this->closes_at->gt(now())
            && $this->shop->isActive();
    }

    public function quotaTotal(): int
    {
        return (int) ($this->quota ?? $this->products->sum('quota'));
    }

    public function soldTotal(): int
    {
        return (int) $this->products->sum('sold_count');
    }

    public function remaining(): int
    {
        return max(0, $this->quotaTotal() - $this->soldTotal());
    }

    public function url(): string
    {
        return '/'.$this->shop->slug.'/'.$this->slug;
    }

    public function posterUrl(): ?string
    {
        return $this->poster_path ? Storage::disk('public')->url($this->poster_path) : null;
    }

    public function priceFrom(): int
    {
        return (int) $this->products->flatMap(fn (Product $p) => $p->variants->map(fn ($v) => $p->price + $v->price_delta))
            ->whenEmpty(fn ($c) => $this->products->pluck('price'))
            ->min();
    }
}
