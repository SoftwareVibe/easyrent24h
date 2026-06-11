<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $guarded = [];

    protected $casts = [
        'activate_shipping' => 'bool',
        'endpoints_only' => 'bool',
        'translations' => 'array',
    ];

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }
}
