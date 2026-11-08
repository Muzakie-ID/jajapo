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
            'out_for_delivery_at' => 'datetime',
            'reminded_at' => 'datetime',
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

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
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

    /** "FTP" atau "Antar ke FEB" */
    public function placeLabel(): string
    {
        return $this->isDelivery() ? 'Antar ke '.($this->deliveryZone?->name ?? 'alamat pembeli') : ($this->slot?->place ?? '-');
    }

    /** "FTP, Sab 14 Nov, 10.00–12.00" atau "Antar ke FEB, Sab 14 Nov, 10.00–14.00" */
    public function slotLabel(): string
    {
        if ($this->isDelivery()) {
            $po = $this->preorder;
            if (! $po?->delivery_starts_at) {
                return $this->placeLabel();
            }

            return $this->placeLabel().', '.str_replace(',', '', tanggal($po->delivery_starts_at)).', '.jam($po->delivery_starts_at).'–'.jam($po->delivery_ends_at);
        }

        return $this->slot ? $this->slot->label() : '-';
    }
}
