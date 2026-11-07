<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function int(string $key): int
    {
        $value = self::query()->whereKey($key)->value('value');

        return (int) ($value ?? config("jajapo.defaults.{$key}"));
    }
}
