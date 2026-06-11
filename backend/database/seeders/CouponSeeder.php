<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

/**
 * Coupon reali rilevati dal sito (affiliati): bartoloparcheggio 5% è anche
 * in eccezione per l'hub Agerola (vedi SettingsSeeder coupon_hub_exceptions).
 */
class CouponSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'bartoloparcheggio', 'percent' => 5],
            ['code' => 'easyrentcoupon17', 'percent' => 5],
        ] as $coupon) {
            Coupon::updateOrCreate(['code' => $coupon['code']], ['percent' => $coupon['percent'], 'active' => true]);
        }
    }
}
