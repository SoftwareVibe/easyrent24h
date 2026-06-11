<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Extra extends Model
{
    protected $guarded = [];

    protected $casts = [
        'always_included' => 'bool',
        'translations' => 'array',
    ];

    public function conditionalPrices(): HasMany
    {
        return $this->hasMany(ExtraPrice::class);
    }
}
