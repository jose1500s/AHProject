<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommodityPriceAlert extends Model
{
    protected $fillable = [
        'item_id', 'triggered_price_copper', 'median_price_copper', 'percent_below',
    ];
}