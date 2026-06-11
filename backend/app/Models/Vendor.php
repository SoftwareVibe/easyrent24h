<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'bool'];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    /** Ordini generati dal coupon del vendor (pagati o con acconto). */
    public function orders()
    {
        return Order::query()
            ->where('coupon_code', $this->coupon?->code)
            ->whereIn('status', ['deposit_paid', 'paid']);
    }

    public function commissionAccrued(): float
    {
        if (! $this->coupon) {
            return 0.0;
        }

        return round((float) $this->orders()->sum('total') * (float) $this->commission_percent / 100, 2);
    }

    public function commissionPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }
}
