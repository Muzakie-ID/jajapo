<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupSlot extends Model
{
    protected $table = 'preorder_pickup_slots';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function preorder(): BelongsTo
    {
        return $this->belongsTo(Preorder::class);
    }

    public function timeLabel(): string
    {
        return jam($this->starts_at).'–'.jam($this->ends_at);
    }

    /** "FTP, Sab 14 Nov, 10.00–12.00" */
    public function label(): string
    {
        return $this->place.', '.str_replace(',', '', tanggal($this->starts_at)).', '.$this->timeLabel();
    }
}
