<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Hub;
use App\Services\Availability\AvailabilityEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\BuildsCatalog;
use Tests\TestCase;

class AvailabilityEngineTest extends TestCase
{
    use BuildsCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function engine(): AvailabilityEngine
    {
        return app(AvailabilityEngine::class);
    }

    public function test_free_vehicle_offers_full_grid(): void
    {
        $vehicle = $this->makeVehicle();

        $result = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-12'),
        );

        $this->assertTrue($result['available']);
        $this->assertSame('08:00', $result['start_slots'][0]);
        $this->assertSame('20:00', end($result['start_slots']));
        $this->assertCount(25, $result['start_slots']); // 08:00..20:00 a step 30'
    }

    public function test_saturated_interior_day_blocks_range(): void
    {
        // Stock 1: una prenotazione che copre un giorno interno del range
        // richiesto rende le date indisponibili (ex templines_check_dates).
        $vehicle = $this->makeVehicle(49, 1);
        Booking::create([
            'vehicle_id' => $vehicle->id,
            'date_start' => '2026-07-11', 'date_end' => '2026-07-11',
            'time_start' => '10:00', 'time_end' => '18:00',
            'status' => Booking::STATUS_CONFIRMED, 'quantity' => 1, 'days' => 1,
        ]);

        $result = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-12'),
        );

        $this->assertFalse($result['available']);
    }

    public function test_boundary_day_reusable_after_return_time(): void
    {
        // Riconsegna alle 10:00: lo stesso mezzo è rinoleggiabile lo stesso
        // giorno dagli slot successivi (il cuore della personalizzazione).
        $vehicle = $this->makeVehicle(49, 1);
        Booking::create([
            'vehicle_id' => $vehicle->id,
            'date_start' => '2026-07-08', 'date_end' => '2026-07-10',
            'time_start' => '15:00', 'time_end' => '10:00',
            'status' => Booking::STATUS_CONFIRMED, 'quantity' => 1, 'days' => 3,
        ]);

        $result = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-10'),
        );

        $this->assertTrue($result['available']);
        // 08:00..10:00 occupati dalla riconsegna, primo slot libero 10:30
        $this->assertSame('10:30', $result['start_slots'][0]);
    }

    public function test_stock_two_allows_overlap(): void
    {
        $vehicle = $this->makeVehicle(99, 2);
        Booking::create([
            'vehicle_id' => $vehicle->id,
            'date_start' => '2026-07-10', 'date_end' => '2026-07-12',
            'time_start' => '09:00', 'time_end' => '18:00',
            'status' => Booking::STATUS_CONFIRMED, 'quantity' => 1, 'days' => 3,
        ]);

        $result = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-12'),
        );

        $this->assertTrue($result['available']);
    }

    public function test_manual_block_makes_range_unavailable(): void
    {
        $vehicle = $this->makeVehicle(49, 3);
        Booking::create([
            'vehicle_id' => $vehicle->id,
            'date_start' => '2026-07-11', 'date_end' => '2026-07-11',
            'status' => Booking::STATUS_BLOCK, 'quantity' => 1, 'days' => 1,
        ]);

        $result = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-12'),
        );

        $this->assertFalse($result['available']);
    }

    public function test_hub_conflict_excludes_slot(): void
    {
        // Un altro ritiro alle 09:00 nello stesso hub esclude lo slot 09:00
        // (ex getFasceDaEscludere: lo staff non può essere in due posti).
        $hub = Hub::create(['name' => 'Agerola']);
        $agerola = $this->makeLocation('Agerola', $hub);
        $amalfi = $this->makeLocation('Amalfi', $hub);

        $vehicleA = $this->makeVehicle(49, 1);
        $vehicleB = $this->makeVehicle(59, 1);
        Booking::create([
            'vehicle_id' => $vehicleA->id,
            'date_start' => '2026-07-10', 'date_end' => '2026-07-11',
            'time_start' => '09:00', 'time_end' => '18:00',
            'pickup_location_id' => $amalfi->id,
            'status' => Booking::STATUS_CONFIRMED, 'quantity' => 1, 'days' => 2,
        ]);

        $result = $this->engine()->check(
            $vehicleB,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-10'),
            $agerola,
        );

        $this->assertTrue($result['available']);
        $this->assertNotContains('09:00', $result['start_slots']);
        $this->assertContains('09:30', $result['start_slots']);
    }

    public function test_endpoints_only_location_restricts_to_open_close(): void
    {
        // Ex fasciaInizioPerLuoghiSpecifici: ad Amalfi solo 08:00 o 20:00.
        $amalfi = $this->makeLocation('Amalfi', null, ['endpoints_only' => true]);
        $vehicle = $this->makeVehicle();

        $result = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-12'),
            $amalfi,
        );

        $this->assertTrue($result['available']);
        $this->assertSame(['08:00', '20:00'], $result['start_slots']);
    }

    public function test_window_start_location_starts_later(): void
    {
        // Ex: Positano/Praiano/Sorrento dalle 09:00.
        $positano = $this->makeLocation('Positano', null, ['window_start' => '09:00']);
        $vehicle = $this->makeVehicle();

        $result = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-12'),
            $positano,
        );

        $this->assertSame('09:00', $result['start_slots'][0]);
    }

    public function test_same_day_end_must_follow_start(): void
    {
        $vehicle = $this->makeVehicle();

        $result = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-10'),
            timeStart: '14:00',
        );

        $this->assertTrue($result['available']);
        $this->assertSame('14:30', $result['end_slots'][0]);
    }

    public function test_no_same_day_vehicle_rejected_for_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 09:00', 'Europe/Rome'));
        $vehicle = $this->makeVehicle(89, 1, ['no_same_day' => true]);

        $today = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-11'),
        );
        $tomorrow = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-11'),
            CarbonImmutable::parse('2026-07-12'),
        );

        $this->assertFalse($today['available']);
        $this->assertTrue($tomorrow['available']);
    }

    public function test_today_slots_respect_lead_time(): void
    {
        // Preavviso minimo 30': alle 11:50 il primo slot proposto è 12:30.
        Carbon::setTestNow(Carbon::parse('2026-07-10 11:50', 'Europe/Rome'));
        $vehicle = $this->makeVehicle();

        $result = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-10'),
        );

        $this->assertSame('12:30', $result['start_slots'][0]);
    }

    public function test_full_saturation_with_chosen_times_fails(): void
    {
        // Stock 1 e mezzo già fuori 09:00-18:00: richiesta sovrapposta KO.
        $vehicle = $this->makeVehicle(49, 1);
        Booking::create([
            'vehicle_id' => $vehicle->id,
            'date_start' => '2026-07-10', 'date_end' => '2026-07-10',
            'time_start' => '09:00', 'time_end' => '18:00',
            'status' => Booking::STATUS_CONFIRMED, 'quantity' => 1, 'days' => 1,
        ]);

        $result = $this->engine()->check(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-10'),
            timeStart: '10:00',
            timeEnd: '12:00',
        );

        $this->assertFalse($result['available']);
    }
}
