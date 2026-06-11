<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price_on_request' => 'bool',
        'no_same_day' => 'bool',
        'active' => 'bool',
        'gallery' => 'array',
        'translations' => 'array',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class);
    }

    public function pickupLocations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_vehicle');
    }

    public function dropoffLocations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'dropoff_location_vehicle');
    }

    public function extras(): BelongsToMany
    {
        return $this->belongsToMany(Extra::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(VehiclePrice::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function basePrice(): ?float
    {
        $row = $this->prices->firstWhere('price_condition_id', null);

        return $row?->price !== null ? (float) $row->price : null;
    }
}
