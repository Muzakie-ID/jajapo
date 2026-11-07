<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Area antar sebuah PO beserta ongkirnya, misalnya "FEB" Rp3.000. */
class DeliveryZone extends Model
{
    protected $table = 'preorder_delivery_zones';

    protected $guarded = ['id'];

    public function preorder(): BelongsTo
    {
        return $this->belongsTo(Preorder::class);
    }
}
