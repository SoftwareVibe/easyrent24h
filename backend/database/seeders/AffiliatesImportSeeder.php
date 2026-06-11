<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Import affiliati dal sito attuale (data/affiliates-export.json):
 * 65 coupon WooCommerce reali + il vendor legacy con i suoi pagamenti.
 */
class AffiliatesImportSeeder extends Seeder
{
    private const EXPORT_PATH = __DIR__.'/../../../data/affiliates-export.json';

    public function run(): void
    {
        $path = realpath(self::EXPORT_PATH) ?: self::EXPORT_PATH;
        if (! is_file($path)) {
            $this->command?->warn("Export affiliati non trovato ($path): salto.");

            return;
        }

        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        // Coupon WooCommerce: i percent diventano coupon attivi con lo stesso
        // sconto; i fixed_* vengono importati disattivi (da rivedere a mano).
        foreach ($data['shop_coupons'] ?? [] as $coupon) {
            $isPercent = ($coupon['discount_type'] ?? '') === 'percent';
            Coupon::updateOrCreate(
                ['code' => $coupon['code']],
                [
                    'percent' => $isPercent ? (float) ($coupon['amount'] ?? 0) : 0,
                    'active' => $isPercent,
                ],
            );
        }

        // Vendor legacy (backend_rapp era quasi vuoto: 1 vendor reale)
        foreach ($data['vendors'] ?? [] as $legacy) {
            $coupon = Coupon::firstOrCreate(
                ['code' => $legacy['coupon'] ?? 'test'],
                ['percent' => 0, 'active' => false],
            );

            $user = User::firstOrCreate(
                ['email' => Str::slug($legacy['username'] ?? 'vendor', '.').'@vendor.easyrent24h.local'],
                [
                    'name' => $legacy['username'] ?? 'Vendor',
                    'password' => Hash::make(Str::random(24)),
                    'role' => 'vendor',
                ],
            );

            $vendor = Vendor::updateOrCreate(
                ['legacy_id' => $legacy['id'] ?? null],
                [
                    'name' => $legacy['username'] ?? 'Vendor',
                    'coupon_id' => $coupon->id,
                    'commission_percent' => 5, // il vecchio gestionale non salvava la %; default storico
                    'user_id' => $user->id,
                    'active' => ($legacy['stato'] ?? '') === 'Attivo',
                ],
            );

            foreach ($data['payments'] ?? [] as $payment) {
                if ((int) ($payment['idVendor'] ?? 0) === (int) ($legacy['id'] ?? -1)) {
                    VendorPayment::firstOrCreate(
                        [
                            'vendor_id' => $vendor->id,
                            'amount' => (float) $payment['amount'],
                            'paid_at' => $payment['created'],
                        ],
                        ['note' => 'Import storico'],
                    );
                }
            }
        }

        $this->command?->info(sprintf(
            'Affiliati: %d coupon (%d attivi), %d vendor, %d pagamenti storici.',
            Coupon::count(), Coupon::where('active', true)->count(),
            Vendor::count(), VendorPayment::count(),
        ));
    }
}
