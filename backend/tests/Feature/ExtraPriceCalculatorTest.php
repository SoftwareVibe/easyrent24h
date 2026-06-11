<?php

namespace Tests\Feature;

use App\Models\Extra;
use App\Models\ExtraPrice;
use App\Models\PriceCondition;
use App\Services\Pricing\ExtraPriceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsCatalog;
use Tests\TestCase;

class ExtraPriceCalculatorTest extends TestCase
{
    use BuildsCatalog;
    use RefreshDatabase;

    private ExtraPriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
        $this->calculator = app(ExtraPriceCalculator::class);
    }

    public function test_total_extra_is_flat_regardless_of_days(): void
    {
        // Caso d'oro: Insta360 X3 59€ forfait
        $extra = Extra::create(['name' => 'Insta360 X3', 'price' => 59, 'type' => 'total', 'max_qty' => 1]);

        $total = $this->calculator->total(
            $extra, 1,
            CarbonImmutable::parse('2026-07-01'),
            CarbonImmutable::parse('2026-07-05'),
        );

        $this->assertSame(59.0, $total);
    }

    public function test_day_extra_multiplies_by_days(): void
    {
        $extra = Extra::create(['name' => 'Casco', 'price' => 5, 'type' => 'day', 'max_qty' => 2]);

        $total = $this->calculator->total(
            $extra, 2,
            CarbonImmutable::parse('2026-07-01'),
            CarbonImmutable::parse('2026-07-03'),
        );

        $this->assertSame(30.0, $total); // 5€ x 2 pezzi x 3 giorni
    }

    public function test_location_conditional_price_overrides_base(): void
    {
        // Caso d'oro: "Price Delivery" 25€ di listino ma 5€ con ritiro Agerola
        // e 0€ con ritiro Positano (ex price_cond + condition "Pick up X").
        $agerola = $this->makeLocation('Agerola');
        $positano = $this->makeLocation('Positano');
        $sorrento = $this->makeLocation('Sorrento');

        $extra = Extra::create(['name' => 'Price Delivery', 'price' => 25, 'type' => 'total', 'max_qty' => 1]);

        $condAgerola = PriceCondition::create([
            'name' => 'Pick up Agerola', 'days_from' => 1, 'fixed_price' => true,
            'pickup_location_ids' => [$agerola->id],
        ]);
        $condPositano = PriceCondition::create([
            'name' => 'Pick up Positano', 'days_from' => 1, 'fixed_price' => true,
            'pickup_location_ids' => [$positano->id],
        ]);
        ExtraPrice::create(['extra_id' => $extra->id, 'price_condition_id' => $condAgerola->id, 'price' => 5]);
        ExtraPrice::create(['extra_id' => $extra->id, 'price_condition_id' => $condPositano->id, 'price' => 0]);

        $start = CarbonImmutable::parse('2026-07-01');
        $end = CarbonImmutable::parse('2026-07-02');

        $this->assertSame(5.0, $this->calculator->total($extra, 1, $start, $end, $agerola->id));
        $this->assertSame(0.0, $this->calculator->total($extra, 1, $start, $end, $positano->id));
        // Località senza condizione: prezzo di listino
        $this->assertSame(25.0, $this->calculator->total($extra, 1, $start, $end, $sorrento->id));
    }

    public function test_quantity_clamped_to_max_qty(): void
    {
        $extra = Extra::create(['name' => 'Gadget', 'price' => 15, 'type' => 'total', 'max_qty' => 2]);

        $total = $this->calculator->total(
            $extra, 5,
            CarbonImmutable::parse('2026-07-01'),
            CarbonImmutable::parse('2026-07-01'),
        );

        $this->assertSame(30.0, $total);
    }
}
