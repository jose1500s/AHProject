<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Item extends Model
{
     protected $guarded = [];

    protected function iconUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->icon_url ? asset('storage/' . $this->icon_url) : null,
        );
    }
}
