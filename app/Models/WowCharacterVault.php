<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowCharacterVault extends Model
{
    protected $table = 'wow_character_vault';

    protected $fillable = [
        'character_key',
        'category',
        'slot_index',
        'threshold',
        'progress',
        'unlocked',
        'level',
        'ilvl',
        'synced_at',
    ];

    protected $casts = [
        'unlocked' => 'boolean',
        'synced_at' => 'datetime',
    ];
}