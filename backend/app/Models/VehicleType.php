<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleType extends Model
{
    protected $guarded = [];

    protected $casts = ['translations' => 'array'];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
