<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $fillable = [
        'blizzard_recipe_id',
        'profession_id',
        'name',
        'name_en',
        'produces_item_id',
        'produces_item_id_high',
        'produces_quantity',
        'rank',
        'bonus_ids',
        'icon_url',
    ];

    protected $casts = [
        'bonus_ids' => 'array',
    ];

    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    public function reagents(): HasMany
    {
        return $this->hasMany(RecipeReagent::class);
    }
}