<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowActiveAuction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'character_key', 'item_id', 'item_name', 'quantity',
        'buyout_copper', 'bid_copper', 'time_left_seconds', 'num_bids', 'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];
}