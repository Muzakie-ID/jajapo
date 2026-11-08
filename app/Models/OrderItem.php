<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['components' => 'array'];
    }

    public function isBundle(): bool
    {
        return ! empty($this->components);
    }

    /**
     * Kuota yang dipakai baris ini per produk: paketnya sendiri ditambah setiap menu isinya.
     *
     * @return array<int, int>
     */
    public function quotaUsage(): array
    {
        $usage = [$this->product_id => $this->qty];
        foreach ($this->components ?? [] as $c) {
            $usage[$c['product_id']] = ($usage[$c['product_id']] ?? 0) + $this->qty * $c['qty'];
        }

        return $usage;
    }

    /**
     * Baris untuk rekap produksi: paket dipecah ke menu isinya.
     *
     * @return list<array{name:string, qty:int}>
     */
    public function productionLines(): array
    {
        if (! $this->isBundle()) {
            return [['name' => $this->name, 'qty' => (int) $this->qty]];
        }

        return array_map(fn ($c) => ['name' => $c['name'], 'qty' => (int) ($this->qty * $c['qty'])], $this->components);
    }

    /** "2 Paket Hemat (1 Original + 1 Es Teh)" untuk daftar ambil. */
    public function labelWithContents(): string
    {
        $label = $this->qty.' '.$this->shortName();
        if ($this->isBundle()) {
            $label .= ' ('.collect($this->components)->map(fn ($c) => $c['qty'].' '.$c['name'])->implode(' + ').')';
        }

        return $label;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** Nama varian saja kalau ada ("Original"), selain itu nama produk. */
    public function shortName(): string
    {
        $parts = explode(', ', $this->name, 2);

        return $parts[1] ?? $parts[0];
    }
}
