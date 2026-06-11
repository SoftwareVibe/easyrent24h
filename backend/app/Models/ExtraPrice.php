<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraPrice extends Model
{
    protected $guarded = [];

    public function extra(): BelongsTo
    {
        return $this->belongsTo(Extra::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(PriceCondition::class, 'price_condition_id');
    }
}
