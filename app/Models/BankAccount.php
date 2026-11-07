<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'verified_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** Pengajuan ganti rekening yang belum diperiksa super admin. */
    public function isPendingChange(): bool
    {
        return ! $this->is_active && $this->verified_at === null && $this->rejected_at === null;
    }

    public function label(): string
    {
        return $this->bank.', akhiran '.substr($this->account_number, -4);
    }
}
