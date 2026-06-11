<?php

namespace App\Services\Booking;

use App\Models\Coupon;
use App\Models\Location;
use App\Models\Setting;

/**
 * Validazione coupon (ex WooCommerce URL Coupons + checkIsTrueLocationByCoupon):
 * un coupon legato a un hub non è spendibile per i ritiri in quell'hub
 * (es. bartoloparcheggio -> hub Agerola).
 */
class CouponService
{
    /**
     * @return array{valid: bool, percent: float, message: string|null}
     */
    public function validate(?string $code, ?Location $pickup = null): array
    {
        if (! $code) {
            return ['valid' => false, 'percent' => 0.0, 'message' => null];
        }

        $coupon = Coupon::where('code', $code)->where('active', true)->first();
        if (! $coupon) {
            return ['valid' => false, 'percent' => 0.0, 'message' => __('Coupon not valid')];
        }

        if ($pickup?->hub_id) {
            $pickup->loadMissing('hub');
            foreach ((array) Setting::get('coupon_hub_exceptions', []) as $exception) {
                if (strcasecmp($exception['coupon'] ?? '', $code) === 0
                    && strcasecmp($exception['hub'] ?? '', $pickup->hub->name ?? '') === 0) {
                    return ['valid' => false, 'percent' => 0.0, 'message' => __('Coupon not valid for this location')];
                }
            }
        }

        return ['valid' => true, 'percent' => (float) $coupon->percent, 'message' => null];
    }
}
