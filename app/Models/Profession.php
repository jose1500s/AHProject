<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profession extends Model
{
    protected $fillable = [
        'blizzard_id',
        'name',
        'icon_url',
    ];

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
}