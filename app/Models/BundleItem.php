<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu isi paket: varian menu dan jumlahnya per paket. */
class BundleItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** "1 Mac n Cheese Panggang, Original" */
    public function label(): string
    {
        return $this->qty.' '.$this->product->name.($this->variant ? ', '.$this->variant->name : '');
    }

    public function normalPrice(): int
    {
        return $this->qty * ($this->product->price + ($this->variant?->price_delta ?? 0));
    }
}
