<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowCharacter extends Model
{
    protected $fillable = [
        'character_key', 'name', 'realm', 'class',
        'level', 'ilvl', 'gold_copper', 'last_updated_at',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
    ];
}