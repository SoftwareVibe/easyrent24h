<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsCatalog;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use BuildsCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
    }

    public function test_booking_flow_creates_order_and_occupies_calendar(): void
    {
        $location = $this->makeLocation('Agerola');
        $vehicle = $this->makeVehicle(50, 1);
        $vehicle->pickupLocations()->attach($location);
        $vehicle->dropoffLocations()->attach($location);

        $payload = [
            'vehicle_id' => $vehicle->id,
            'start' => '2026-07-10',
            'end' => '2026-07-12',
            'pick_up' => $location->id,
            'drop_off' => $location->id,
            'time_start' => '10:00',
            'time_end' => '10:00',
            'customer' => ['name' => 'Mario Rossi', 'email' => 'mario@example.com'],
        ];

        $first = $this->postJson('/api/bookings', $payload);
        $first->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('total', 150); // 3 giorni x 50, riconsegna 10:00

        $this->assertSame(1, Order::count());
        $this->assertSame(1, Booking::count());

        // Stesso mezzo, stesse date: il calendario è occupato → 422
        $second = $this->postJson('/api/bookings', $payload);
        $second->assertUnprocessable();
        $this->assertSame(1, Order::count());
    }

    public function test_early_return_booking_charges_one_day_less(): void
    {
        $location = $this->makeLocation('Agerola');
        $vehicle = $this->makeVehicle(50, 1);
        $vehicle->pickupLocations()->attach($location);

        $response = $this->postJson('/api/bookings', [
            'vehicle_id' => $vehicle->id,
            'start' => '2026-07-10',
            'end' => '2026-07-12',
            'pick_up' => $location->id,
            'time_start' => '10:00',
            'time_end' => '09:00',
            'customer' => ['name' => 'Mario Rossi', 'email' => 'mario@example.com'],
        ]);

        $response->assertCreated()->assertJsonPath('total', 100);
    }

    public function test_pickup_location_must_belong_to_vehicle(): void
    {
        $allowed = $this->makeLocation('Agerola');
        $other = $this->makeLocation('Positano');
        $vehicle = $this->makeVehicle(50, 1);
        $vehicle->pickupLocations()->attach($allowed);

        $response = $this->postJson('/api/bookings', [
            'vehicle_id' => $vehicle->id,
            'start' => '2026-07-10',
            'end' => '2026-07-11',
            'pick_up' => $other->id,
            'time_start' => '10:00',
            'time_end' => '10:00',
            'customer' => ['name' => 'Mario Rossi', 'email' => 'mario@example.com'],
        ]);

        $response->assertUnprocessable();
    }

    public function test_quote_endpoint_returns_slots_and_total(): void
    {
        $location = $this->makeLocation('Positano', null, ['window_start' => '09:00']);
        $vehicle = $this->makeVehicle(99, 2);
        $vehicle->pickupLocations()->attach($location);

        $response = $this->postJson('/api/quote', [
            'vehicle_id' => $vehicle->id,
            'start' => '2026-07-10',
            'end' => '2026-07-10',
            'pick_up' => $location->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('total', 99)
            ->assertJsonPath('start_slots.0', '09:00');
    }
}
