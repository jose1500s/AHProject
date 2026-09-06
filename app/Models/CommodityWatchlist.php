<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommodityWatchlist extends Model
{
    protected $table = 'commodity_watchlist';

    protected $fillable = [
        'item_id', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}