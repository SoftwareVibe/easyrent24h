<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $guarded = [];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public static function generateNumber(): string
    {
        return 'ER-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }
}
