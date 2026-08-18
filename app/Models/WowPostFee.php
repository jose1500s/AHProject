<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowPostFee extends Model
{
    protected $fillable = ['character_key', 'item_name', 'fee_copper', 'occurred_at'];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];
}