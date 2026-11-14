<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Pilihan bank atau e-wallet untuk rekening pencairan. Rekening menyimpan namanya sebagai teks. */
class Bank extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Nama pilihan yang tampil di form, urut abjad. */
    public static function options(): array
    {
        return static::query()->active()->orderByRaw('lower(name)')->pluck('name')->all();
    }

    public function usageCount(): int
    {
        return BankAccount::query()->where('bank', $this->name)->count();
    }
}
