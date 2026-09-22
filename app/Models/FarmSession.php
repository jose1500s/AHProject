<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmSession extends Model
{
    protected $fillable = [
        'character_key',
        'mode',
        'status',
        'planned_duration_minutes',
        'started_at',
        'stopped_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Ventana de tiempo de la sesión: desde started_at hasta stopped_at,
     * o hasta "ahora" si sigue activa.
     */
    public function endBoundary(): \Carbon\Carbon
    {
        return $this->stopped_at ?? now();
    }
}