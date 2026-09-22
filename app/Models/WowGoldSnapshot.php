<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowGoldSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'characters_gold_copper',
        'warband_gold_copper',
        'total_gold_copper',
        'snapshot_at',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
    ];
}