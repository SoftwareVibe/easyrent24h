<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Hub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsCatalog;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use BuildsCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
    }

    public function test_coupon_discounts_order_total(): void
    {
        Coupon::create(['code' => 'sconto10', 'percent' => 10]);
        $location = $this->makeLocation('Maiori');
        $vehicle = $this->makeVehicle(50, 1);
        $vehicle->pickupLocations()->attach($location);

        $response = $this->postJson('/api/bookings', [
            'vehicle_id' => $vehicle->id,
            'start' => '2026-07-10',
            'end' => '2026-07-12',
            'pick_up' => $location->id,
            'time_start' => '10:00',
            'time_end' => '10:00',
            'customer' => ['name' => 'Mario', 'email' => 'm@example.com', 'coupon_code' => 'sconto10'],
        ]);

        // 150 - 10% = 135, acconto 25% = 33.75
        $response->assertCreated()
            ->assertJsonPath('total', 135)
            ->assertJsonPath('deposit_amount', 33.75);
    }

    public function test_hub_exception_blocks_coupon(): void
    {
        // Replica checkIsTrueLocationByCoupon: bartoloparcheggio non vale
        // per i ritiri nell'hub Agerola (da settings coupon_hub_exceptions).
        Coupon::create(['code' => 'bartoloparcheggio', 'percent' => 5]);
        $hub = Hub::create(['name' => 'Agerola']);
        $agerola = $this->makeLocation('Agerola', $hub);
        $vehicle = $this->makeVehicle(50, 1);
        $vehicle->pickupLocations()->attach($agerola);

        $response = $this->postJson('/api/bookings', [
            'vehicle_id' => $vehicle->id,
            'start' => '2026-07-10',
            'end' => '2026-07-12',
            'pick_up' => $agerola->id,
            'time_start' => '10:00',
            'time_end' => '10:00',
            'customer' => ['name' => 'Mario', 'email' => 'm@example.com', 'coupon_code' => 'bartoloparcheggio'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    }

    public function test_unknown_coupon_rejected(): void
    {
        $location = $this->makeLocation('Maiori');
        $vehicle = $this->makeVehicle(50, 1);
        $vehicle->pickupLocations()->attach($location);

        $this->postJson('/api/bookings', [
            'vehicle_id' => $vehicle->id,
            'start' => '2026-07-10',
            'end' => '2026-07-11',
            'pick_up' => $location->id,
            'time_start' => '10:00',
            'time_end' => '10:00',
            'customer' => ['name' => 'Mario', 'email' => 'm@example.com', 'coupon_code' => 'finto'],
        ])->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    }

    public function test_validate_endpoint(): void
    {
        Coupon::create(['code' => 'sconto10', 'percent' => 10]);

        $this->postJson('/api/coupons/validate', ['code' => 'sconto10'])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('percent', 10);

        $this->postJson('/api/coupons/validate', ['code' => 'inesistente'])
            ->assertOk()
            ->assertJsonPath('valid', false);
    }
}
