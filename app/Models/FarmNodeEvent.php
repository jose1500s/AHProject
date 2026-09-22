<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmNodeEvent extends Model
{
    protected $fillable = [
        'character_key',
        'profession',
        'item_id',
        'quantity',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /**
     * Eventos de un personaje dentro de una ventana de tiempo dada
     * (usado para cruzar contra una FarmSession).
     */
    public function scopeInWindow($query, string $characterKey, $start, $end)
    {
        return $query->where('character_key', $characterKey)
            ->whereBetween('occurred_at', [$start, $end]);
    }
}