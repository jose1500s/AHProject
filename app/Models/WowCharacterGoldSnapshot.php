<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowCharacterGoldSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'character_key',
        'gold_copper',
        'snapshot_at',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
    ];
}