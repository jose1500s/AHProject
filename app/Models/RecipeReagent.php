<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeReagent extends Model
{
    protected $fillable = [
        'recipe_id',
        'item_id',
        'item_id_high',
        'quantity',
        'is_optional',
    ];

    protected $casts = [
        'is_optional' => 'boolean',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}