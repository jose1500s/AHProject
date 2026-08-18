<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowWarband extends Model
{
    protected $table = 'wow_warband';

    protected $fillable = ['gold_copper', 'last_updated_at'];

    protected $casts = [
        'last_updated_at' => 'datetime',
    ];
}