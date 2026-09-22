<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WowGoldGoal extends Model
{
    protected $fillable = [
        'title',
        'target_gold_copper',
        'deadline_date',
    ];

    protected $casts = [
        'deadline_date' => 'date',
    ];
}