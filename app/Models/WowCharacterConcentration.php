<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowCharacterConcentration extends Model
{
    protected $table = 'wow_character_concentration';

    protected $fillable = [
        'character_key',
        'profession',
        'currency_id',
        'quantity',
        'max_quantity',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];
}