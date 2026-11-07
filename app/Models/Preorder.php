<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Preorder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

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
            'delivery_starts_at' => 'datetime',
            'delivery_ends_at' => 'datetime',
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

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class)->orderBy('sort');
    }

    /** Antar aktif kalau dinyalakan dan punya minimal satu area. */
    public function offersDelivery(): bool
    {
        return $this->delivery_enabled && $this->deliveryZones->isNotEmpty();
    }

    /** "Sab, 14 Nov, 10.00–14.00" */
    public function deliveryLabel(): ?string
    {
        if (! $this->delivery_starts_at) {
            return null;
        }

        return tanggal($this->delivery_starts_at).', '.jam($this->delivery_starts_at).'–'.jam($this->delivery_ends_at);
    }

    /** Akhir layanan PO: slot ambil terakhir atau akhir jadwal antar, mana yang lebih lambat. */
    public function serviceEndsAt(): ?Carbon
    {
        return collect([$this->slots->max('ends_at'), $this->delivery_enabled ? $this->delivery_ends_at : null])->filter()->max();
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

    /** PO yang boleh dilihat pembeli: bukan draf. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_CLOSED]);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** Tahap untuk panel penjual: draft, scheduled, open, closed, cancelled. */
    public function stage(): string
    {
        return match (true) {
            $this->status === self::STATUS_DRAFT => 'draft',
            $this->status === self::STATUS_CANCELLED => 'cancelled',
            $this->status === self::STATUS_CLOSED || $this->closes_at->lte(now()) => 'closed',
            $this->opens_at->gt(now()) => 'scheduled',
            default => 'open',
        };
    }

    public function storyUrl(): ?string
    {
        return $this->story_image_path ? Storage::disk('public')->url($this->story_image_path) : null;
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
