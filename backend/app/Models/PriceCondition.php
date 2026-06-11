<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceCondition extends Model
{
    protected $guarded = [];

    protected $casts = [
        'fixed_price' => 'bool',
        'active' => 'bool',
        'weekdays' => 'array',
        'month_days' => 'array',
        'months' => 'array',
        'years' => 'array',
        'pickup_location_ids' => 'array',
        'dropoff_location_ids' => 'array',
        'vehicle_type_ids' => 'array',
        'date_from' => 'date',
        'date_to' => 'date',
    ];
}
