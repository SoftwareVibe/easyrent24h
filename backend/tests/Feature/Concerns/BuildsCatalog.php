<?php

namespace Tests\Feature\Concerns;

use App\Models\Hub;
use App\Models\Location;
use App\Models\Vehicle;
use App\Models\VehiclePrice;
use Database\Seeders\SettingsSeeder;

trait BuildsCatalog
{
    protected function seedSettings(): void
    {
        (new SettingsSeeder)->run();
    }

    protected function makeVehicle(float $basePrice = 49, int $stock = 1, array $attrs = []): Vehicle
    {
        $vehicle = Vehicle::create(array_merge([
            'name' => 'Test Scooter',
            'slug' => 'test-scooter-'.uniqid(),
            'stock' => $stock,
        ], $attrs));

        VehiclePrice::create([
            'vehicle_id' => $vehicle->id,
            'price_condition_id' => null,
            'price' => $basePrice,
        ]);

        return $vehicle;
    }

    protected function makeLocation(string $name, ?Hub $hub = null, array $attrs = []): Location
    {
        return Location::create(array_merge([
            'name' => $name,
            'slug' => str($name)->slug()->toString().'-'.uniqid(),
            'hub_id' => $hub?->id,
        ], $attrs));
    }
}
