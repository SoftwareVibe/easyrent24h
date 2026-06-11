<?php

namespace Tests\Feature;

use App\Services\Booking\QuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsCatalog;
use Tests\TestCase;

class QuoteServiceTest extends TestCase
{
    use BuildsCatalog;
    use RefreshDatabase;

    private QuoteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
        $this->service = app(QuoteService::class);
    }

    public function test_early_return_discounts_last_day(): void
    {
        // Caso d'oro "giorno in meno": 3 giorni con riconsegna alle 09:00
        // (<= 09:30) si pagano 2 giorni; alle 10:00 si pagano 3.
        $vehicle = $this->makeVehicle(50);
        $start = CarbonImmutable::parse('2026-07-10');
        $end = CarbonImmutable::parse('2026-07-12');

        $early = $this->service->quote($vehicle, $start, $end, timeStart: '10:00', timeEnd: '09:00');
        $late = $this->service->quote($vehicle, $start, $end, timeStart: '10:00', timeEnd: '10:00');

        $this->assertSame(2, $early['days']);
        $this->assertSame(100.0, $early['total']);

        $this->assertSame(3, $late['days']);
        $this->assertSame(150.0, $late['total']);
    }

    public function test_early_return_not_applied_same_day(): void
    {
        $vehicle = $this->makeVehicle(50);
        $day = CarbonImmutable::parse('2026-07-10');

        $quote = $this->service->quote($vehicle, $day, $day, timeStart: '09:00', timeEnd: '12:00');

        $this->assertSame(1, $quote['days']);
        $this->assertSame(50.0, $quote['total']);
    }

    public function test_quote_without_times_assumes_early_return(): void
    {
        // Come il sito attuale: al primo calcolo (orari non scelti) il
        // preventivo multi-giorno parte dall'ipotesi riconsegna mattutina.
        $vehicle = $this->makeVehicle(50);

        $quote = $this->service->quote(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-12'),
        );

        $this->assertSame(2, $quote['days']);
        $this->assertSame(100.0, $quote['total']);
    }

    public function test_quantity_multiplies_total(): void
    {
        $vehicle = $this->makeVehicle(40, 3);

        $quote = $this->service->quote(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-10'),
            quantity: 2,
        );

        $this->assertSame(80.0, $quote['total']);
    }

    public function test_minimum_days_enforced(): void
    {
        $vehicle = $this->makeVehicle(40, 1, ['min_days' => 3]);

        $quote = $this->service->quote(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-11'),
        );

        $this->assertFalse($quote['available']);
    }
}
