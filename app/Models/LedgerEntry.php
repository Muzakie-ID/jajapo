<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris buku besar. Saldo selalu dihitung dari jumlah baris, tidak pernah ditimpa.
 */
class LedgerEntry extends Model
{
    public const HELD = 'held';

    public const AVAILABLE = 'available';

    public const PLATFORM_REVENUE = 'platform_revenue';

    public const GATEWAY_CLEARING = 'gateway_clearing';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
