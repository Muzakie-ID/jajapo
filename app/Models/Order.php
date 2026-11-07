<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function preorder(): BelongsTo
    {
        return $this->belongsTo(Preorder::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(PickupSlot::class, 'pickup_slot_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isDelivery(): bool
    {
        return $this->fulfillment === 'delivery';
    }

    public function isPaid(): bool
    {
        return in_array($this->status->value, OrderStatus::paidStates(), true);
    }

    /** Bagian toko: subtotal ditambah ongkir. Kode unik milik platform. */
    public function shopAmount(): int
    {
        return $this->subtotal + $this->delivery_fee;
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('qty');
    }

    /** "2 Original, 1 Extra keju" */
    public function itemsLabel(): string
    {
        return $this->items->map(fn (OrderItem $i) => $i->qty.' '.$i->shortName())->implode(', ');
    }

    public function placeLabel(): string
    {
        return $this->isDelivery() ? 'Antar: '.($this->address_note ?: 'alamat belum diisi') : ($this->slot?->point->name ?? '-');
    }

    public function slotLabel(): string
    {
        if ($this->isDelivery()) {
            return 'Antar ke '.($this->address_note ?: 'fakultas');
        }

        return $this->slot ? $this->slot->label() : '-';
    }
}
