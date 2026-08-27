<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowCharacterInventory extends Model
{
    protected $table = 'wow_character_inventory';

    public $timestamps = false;

    protected $fillable = [
        'character_key', 'location', 'item_id', 'quantity', 'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];
}