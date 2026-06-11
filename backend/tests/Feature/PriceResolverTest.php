<?php

namespace Tests\Feature;

use App\Models\PriceCondition;
use App\Models\VehiclePrice;
use App\Services\Pricing\PriceResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsCatalog;
use Tests\TestCase;

class PriceResolverTest extends TestCase
{
    use BuildsCatalog;
    use RefreshDatabase;

    private PriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
        $this->resolver = app(PriceResolver::class);
    }

    public function test_base_price_per_day_inclusive_of_both_ends(): void
    {
        // Caso d'oro: Piaggio Liberty 49€/giorno, 3 giorni = 147€
        $vehicle = $this->makeVehicle(49);

        $result = $this->resolver->resolve(
            $vehicle,
            CarbonImmutable::parse('2026-07-01'),
            CarbonImmutable::parse('2026-07-03'),
        );

        $this->assertSame(3, $result['days']);
        $this->assertSame(147.0, $result['total']);
    }

    public function test_single_day_rental(): void
    {
        $vehicle = $this->makeVehicle(99);

        $result = $this->resolver->resolve(
            $vehicle,
            CarbonImmutable::parse('2026-07-01'),
            CarbonImmutable::parse('2026-07-01'),
        );

        $this->assertSame(1, $result['days']);
        $this->assertSame(99.0, $result['total']);
    }

    public function test_seasonal_condition_prices_only_matching_days(): void
    {
        // Ex condition "Agosto Aumento prezzo" (months=08): i giorni di agosto
        // usano il prezzo condizionale, gli altri la tariffa base.
        $vehicle = $this->makeVehicle(50);
        $august = PriceCondition::create([
            'name' => 'Agosto', 'months' => ['08'], 'fixed_price' => false,
        ]);
        VehiclePrice::create([
            'vehicle_id' => $vehicle->id, 'price_condition_id' => $august->id, 'price' => 80,
        ]);

        $result = $this->resolver->resolve(
            $vehicle,
            CarbonImmutable::parse('2026-07-30'),
            CarbonImmutable::parse('2026-08-02'),
        );

        // 30/07 e 31/07 a 50, 01/08 e 02/08 a 80
        $this->assertSame(4, $result['days']);
        $this->assertSame(260.0, $result['total']);
    }

    public function test_duration_condition_twins_resolved_by_highest_days_from(): void
    {
        // Due condizioni identiche salvo days_from (3+ e 7+): per un noleggio
        // di 8 giorni vince la 7+ (tie-break del motore originale).
        $vehicle = $this->makeVehicle(100);
        $threePlus = PriceCondition::create(['name' => 'Sconto 3+', 'days_from' => 3]);
        $sevenPlus = PriceCondition::create(['name' => 'Sconto 7+', 'days_from' => 7]);
        VehiclePrice::create(['vehicle_id' => $vehicle->id, 'price_condition_id' => $threePlus->id, 'price' => 90]);
        VehiclePrice::create(['vehicle_id' => $vehicle->id, 'price_condition_id' => $sevenPlus->id, 'price' => 70]);

        $result = $this->resolver->resolve(
            $vehicle,
            CarbonImmutable::parse('2026-07-01'),
            CarbonImmutable::parse('2026-07-08'),
        );

        $this->assertSame(8, $result['days']);
        $this->assertSame(8 * 70.0, $result['total']);
    }

    public function test_fixed_price_condition_charged_once(): void
    {
        // Condizione forfait (ex fixed_price): prezzo applicato una volta sola.
        $vehicle = $this->makeVehicle(100);
        $package = PriceCondition::create(['name' => 'Weekend package', 'days_from' => 2, 'fixed_price' => true]);
        VehiclePrice::create(['vehicle_id' => $vehicle->id, 'price_condition_id' => $package->id, 'price' => 150]);

        $result = $this->resolver->resolve(
            $vehicle,
            CarbonImmutable::parse('2026-07-04'),
            CarbonImmutable::parse('2026-07-05'),
        );

        $this->assertSame(150.0, $result['total']);
    }

    public function test_pickup_location_condition(): void
    {
        // Ex condition "Pick up X": vale solo per la località di ritiro indicata.
        $vehicle = $this->makeVehicle(60);
        $agerola = $this->makeLocation('Agerola');
        $positano = $this->makeLocation('Positano');
        $condition = PriceCondition::create([
            'name' => 'Pick up Agerola', 'days_from' => 1, 'fixed_price' => true,
            'pickup_location_ids' => [$agerola->id],
        ]);
        VehiclePrice::create(['vehicle_id' => $vehicle->id, 'price_condition_id' => $condition->id, 'price' => 999]);

        $fromPositano = $this->resolver->resolve(
            $vehicle,
            CarbonImmutable::parse('2026-07-01'),
            CarbonImmutable::parse('2026-07-01'),
            $positano->id,
        );
        $fromAgerola = $this->resolver->resolve(
            $vehicle,
            CarbonImmutable::parse('2026-07-01'),
            CarbonImmutable::parse('2026-07-01'),
            $agerola->id,
        );

        $this->assertSame(60.0, $fromPositano['total']);
        $this->assertSame(999.0, $fromAgerola['total']);
    }

    public function test_condition_without_price_row_falls_back_to_base(): void
    {
        $vehicle = $this->makeVehicle(45);
        PriceCondition::create(['name' => 'Senza listino', 'months' => ['07']]);

        $result = $this->resolver->resolve(
            $vehicle,
            CarbonImmutable::parse('2026-07-10'),
            CarbonImmutable::parse('2026-07-11'),
        );

        $this->assertSame(90.0, $result['total']);
    }
}
