<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AffiliatesImportSeeder;
use Database\Seeders\CatalogImportSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gate "admin senza errori", parte 2: i SALVATAGGI.
 * Per ogni risorsa: round-trip di modifica sul primo record REALE
 * (apri form precompilato -> salva -> nessun errore) e creazione di un
 * nuovo record con dati minimi. È qui che emergono i bug di cast/json.
 */
class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            SettingsSeeder::class,
            CatalogImportSeeder::class,
            AffiliatesImportSeeder::class,
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $vehicle = \App\Models\Vehicle::first();
        $location = \App\Models\Location::first();
        $order = \App\Models\Order::create([
            'number' => 'ER-TEST-1', 'status' => 'deposit_paid',
            'customer_name' => 'Mario', 'customer_email' => 'm@example.com',
            'subtotal' => 100, 'total' => 100, 'deposit_amount' => 25, 'paid_total' => 25,
        ]);
        $order->bookings()->create([
            'vehicle_id' => $vehicle->id, 'date_start' => '2026-07-10', 'date_end' => '2026-07-12',
            'time_start' => '10:00', 'time_end' => '10:00',
            'pickup_location_id' => $location->id, 'status' => 'confirmed', 'days' => 3, 'price' => 100,
            'extras' => [['extra_id' => 1, 'name' => 'Gadget', 'qty' => 1, 'total' => 5]],
        ]);
        \App\Models\ContactMessage::create([
            'name' => 'Mario', 'email' => 'm@example.com', 'message' => 'Ciao', 'locale' => 'it',
        ]);
        \App\Models\VendorPayment::firstOrCreate(
            ['vendor_id' => \App\Models\Vendor::first()->id, 'amount' => 10, 'paid_at' => '2026-06-01'],
        );
    }

    /** Round-trip: apri l'edit del primo record reale e salva senza modifiche. */
    private function assertEditSaves(string $editPage, $record): void
    {
        Livewire::test($editPage, ['record' => $record->getKey()])
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_edit_save_roundtrip_on_real_records(): void
    {
        $this->assertEditSaves(\App\Filament\Resources\Vehicles\Pages\EditVehicle::class, \App\Models\Vehicle::first());
        $this->assertEditSaves(\App\Filament\Resources\Locations\Pages\EditLocation::class, \App\Models\Location::first());
        $this->assertEditSaves(\App\Filament\Resources\Hubs\Pages\EditHub::class, \App\Models\Hub::first());
        $this->assertEditSaves(\App\Filament\Resources\PriceConditions\Pages\EditPriceCondition::class, \App\Models\PriceCondition::first());
        $this->assertEditSaves(\App\Filament\Resources\Extras\Pages\EditExtra::class, \App\Models\Extra::first());
        $this->assertEditSaves(\App\Filament\Resources\Coupons\Pages\EditCoupon::class, \App\Models\Coupon::first());
        $this->assertEditSaves(\App\Filament\Resources\Bookings\Pages\EditBooking::class, \App\Models\Booking::first());
        $this->assertEditSaves(\App\Filament\Resources\Orders\Pages\EditOrder::class, \App\Models\Order::first());
        $this->assertEditSaves(\App\Filament\Resources\Vendors\Pages\EditVendor::class, \App\Models\Vendor::first());
        $this->assertEditSaves(\App\Filament\Resources\VendorPayments\Pages\EditVendorPayment::class, \App\Models\VendorPayment::first());
        $this->assertEditSaves(\App\Filament\Resources\ContactMessages\Pages\EditContactMessage::class, \App\Models\ContactMessage::first());
        $this->assertEditSaves(\App\Filament\Resources\Settings\Pages\EditSetting::class, \App\Models\Setting::first());
    }

    public function test_create_vehicle_with_pickup_locations(): void
    {
        $location = \App\Models\Location::first();

        Livewire::test(\App\Filament\Resources\Vehicles\Pages\CreateVehicle::class)
            ->fillForm([
                'name' => 'Vespa Test Admin',
                'slug' => 'vespa-test-admin',
                'stock' => 2,
                'sort_order' => 0,
                'active' => true,
                'pickupLocations' => [$location->id],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $vehicle = \App\Models\Vehicle::where('slug', 'vespa-test-admin')->first();
        $this->assertNotNull($vehicle);
        $this->assertTrue($vehicle->pickupLocations->contains($location));
    }

    public function test_create_manual_block_booking(): void
    {
        $vehicle = \App\Models\Vehicle::first();

        Livewire::test(\App\Filament\Resources\Bookings\Pages\CreateBooking::class)
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'date_start' => '2026-08-01',
                'date_end' => '2026-08-05',
                'quantity' => 1,
                'status' => 'block',
                'days' => 5,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bookings', [
            'vehicle_id' => $vehicle->id,
            'status' => 'block',
            'date_start' => '2026-08-01 00:00:00',
        ]);

        // il blocco deve davvero chiudere il calendario
        $engine = app(\App\Services\Availability\AvailabilityEngine::class);
        $result = $engine->check(
            $vehicle,
            \Carbon\CarbonImmutable::parse('2026-08-02'),
            \Carbon\CarbonImmutable::parse('2026-08-03'),
        );
        $this->assertFalse($result['available']);
    }

    public function test_create_price_condition_and_conditional_price(): void
    {
        Livewire::test(\App\Filament\Resources\PriceConditions\Pages\CreatePriceCondition::class)
            ->fillForm([
                'name' => 'Alta stagione test',
                'days_from' => 0,
                'fixed_price' => false,
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('price_conditions', ['name' => 'Alta stagione test']);
    }

    public function test_create_coupon_location_vendor_payment(): void
    {
        Livewire::test(\App\Filament\Resources\Coupons\Pages\CreateCoupon::class)
            ->fillForm(['code' => 'admincoupon', 'percent' => 7, 'active' => true])
            ->call('create')
            ->assertHasNoErrors();

        Livewire::test(\App\Filament\Resources\Locations\Pages\CreateLocation::class)
            ->fillForm(['name' => 'Ravello', 'slug' => 'ravello'])
            ->call('create')
            ->assertHasNoErrors();

        Livewire::test(\App\Filament\Resources\VendorPayments\Pages\CreateVendorPayment::class)
            ->fillForm([
                'vendor_id' => \App\Models\Vendor::first()->id,
                'amount' => 50,
                'paid_at' => '2026-06-11',
            ])
            ->call('create')
            ->assertHasNoErrors();
    }

    public function test_vehicle_prices_relation_manager_add_and_edit(): void
    {
        $vehicle = \App\Models\Vehicle::first();
        $condition = \App\Models\PriceCondition::first();

        $manager = Livewire::test(\App\Filament\Resources\Vehicles\RelationManagers\PricesRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => \App\Filament\Resources\Vehicles\Pages\EditVehicle::class,
        ]);

        $manager->assertOk();

        $manager->callTableAction('create', data: [
            'price_condition_id' => $condition->id,
            'price' => 88,
        ])->assertHasNoErrors();

        $this->assertDatabaseHas('vehicle_prices', [
            'vehicle_id' => $vehicle->id,
            'price_condition_id' => $condition->id,
            'price' => 88,
        ]);
    }

    public function test_order_cancel_action_frees_calendar(): void
    {
        $order = \App\Models\Order::first();

        Livewire::test(\App\Filament\Resources\Orders\Pages\ListOrders::class)
            ->callTableAction('cancel', $order)
            ->assertHasNoErrors();

        $this->assertContains($order->fresh()->status, ['cancelled', 'refunded']);
        $this->assertSame('cancelled', $order->bookings()->first()->status);
    }

    public function test_setting_edit_keeps_json_value(): void
    {
        $setting = \App\Models\Setting::find('pickup_dropoff_days');

        Livewire::test(\App\Filament\Resources\Settings\Pages\EditSetting::class, ['record' => $setting->getKey()])
            ->fillForm(['value' => '[1,2,3,4,5]'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([1, 2, 3, 4, 5], \App\Models\Setting::get('pickup_dropoff_days'));
    }
}
