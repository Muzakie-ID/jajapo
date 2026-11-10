<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_bundle' => 'boolean'];
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function preorder(): BelongsTo
    {
        return $this->belongsTo(Preorder::class);
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundle_id');
    }

    /** "1 Mac n Cheese Panggang, Original + 1 Es Teh Manis, Reguler" */
    public function contentsLabel(): string
    {
        return $this->bundleItems->map(fn (BundleItem $i) => $i->label())->implode(' + ');
    }

    /** Harga kalau isi paket dibeli satuan. */
    public function normalPrice(): int
    {
        return (int) $this->bundleItems->sum(fn (BundleItem $i) => $i->normalPrice());
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort');
    }
}
