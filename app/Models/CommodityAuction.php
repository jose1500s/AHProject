<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommodityAuction extends Model
{
    protected $fillable = [
        'blizzard_auction_id',
        'item_id',
        'quantity',
        'unit_price',
        'time_left',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'blizzard_id');
    }
}