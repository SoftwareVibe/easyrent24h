<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Configurazione del motore: ex theme mods WordPress + costanti che nel
 * plugin RenRoll erano hardcoded (orari globali, soglia "giorno in meno",
 * buffer prenotazione, eccezioni coupon per hub).
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'day_start' => '08:00',
            'day_end' => '20:00',
            'slot_minutes' => 30,
            'early_return_threshold' => '09:30', // riconsegna <= soglia: ultimo giorno non fatturato
            'lead_minutes' => 30,                // preavviso minimo per oggi
            'cleaning_days' => 0,
            'pickup_dropoff_days' => [1, 2, 3, 4, 5, 6, 7], // ISO weekday ammessi
            'holidays' => [],
            'minimum_days' => 1,
            'maximum_days' => null,
            'timezone' => 'Europe/Rome',
            'currency' => 'EUR',
            'deposit_percent' => 25,
            'coupon_hub_exceptions' => [
                ['coupon' => 'bartoloparcheggio', 'hub' => 'Agerola'],
                ['coupon' => 'easyrentcoupon17', 'hub' => 'Agerola'],
            ],
        ];

        foreach ($defaults as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
