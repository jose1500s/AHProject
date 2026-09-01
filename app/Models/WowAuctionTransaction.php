<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowAuctionTransaction extends Model
{
    protected $fillable = [
        'character_key', 'source_id', 'type', 'item_name', 'item_id', 'quantity', 'counterparty',
        'sale_price_copper', 'deposit_copper', 'consignment_copper',
        'amount_copper', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];
}