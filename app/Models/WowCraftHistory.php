<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowCraftHistory extends Model
{
    protected $table = 'wow_craft_history';

    protected $fillable = [
        'character_key',
        'source_id',
        'item_id',
        'quantity',
        'multicraft',
        'concentration_spent',
        'concentration_currency_id',
        'crafting_quality',
        'is_crit',
        'first_craft_reward',
        'occurred_at',
    ];

    protected $casts = [
        'is_crit' => 'boolean',
        'first_craft_reward' => 'boolean',
        'occurred_at' => 'datetime',
    ];
}