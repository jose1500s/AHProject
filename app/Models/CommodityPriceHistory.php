<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommodityPriceHistory extends Model
{
    protected $table = 'commodity_price_history';

    public $timestamps = false;

    protected $fillable = [
        'item_id',
        'min_price_copper',
        'listings',
        'volume',
        'snapshot_at',
    ];
}