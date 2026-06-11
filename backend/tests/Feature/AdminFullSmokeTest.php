<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AffiliatesImportSeeder;
use Database\Seeders\CatalogImportSeeder;
use Database\Seeders\CouponSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate "admin senza errori": ogni risorsa del pannello deve aprire
 * lista, pagina di creazione e pagina di modifica del primo record
 * CON I DATI REALI importati dal sito (è lì che si annidano gli errori:
 * cast json, relazioni, valori legacy).
 */
class AdminFullSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** slug risorsa => modello (per la pagina edit del primo record) */
    private const RESOURCES = [
        'vehicles' => \App\Models\Vehicle::class,
        'locations' => \App\Models\Location::class,
        'hubs' => \App\Models\Hub::class,
        'price-conditions' => \App\Models\PriceCondition::class,
        'extras' => \App\Models\Extra::class,
        'coupons' => \App\Models\Coupon::class,
        'bookings' => \App\Models\Booking::class,
        'orders' => \App\Models\Order::class,
        'vendors' => \App\Models\Vendor::class,
        'vendor-payments' => \App\Models\VendorPayment::class,
        'contact-messages' => \App\Models\ContactMessage::class,
        'settings' => \App\Models\Setting::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            SettingsSeeder::class,
            CatalogImportSeeder::class,
            CouponSeeder::class,
            AffiliatesImportSeeder::class,
        ]);

        // dati che i seeder non creano: una prenotazione, un ordine, un contatto
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
        $order->payments()->create([
            'provider' => 'offline', 'type' => 'deposit', 'amount' => 25, 'status' => 'succeeded',
        ]);
        \App\Models\ContactMessage::create([
            'name' => 'Mario', 'email' => 'm@example.com', 'message' => 'Ciao', 'locale' => 'it',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_every_resource_list_page_renders(): void
    {
        $admin = $this->admin();
        foreach (array_keys(self::RESOURCES) as $slug) {
            $this->actingAs($admin)->get("/admin/{$slug}")
                ->assertOk();
        }
    }

    public function test_every_resource_create_page_renders(): void
    {
        $admin = $this->admin();
        foreach (array_keys(self::RESOURCES) as $slug) {
            $this->actingAs($admin)->get("/admin/{$slug}/create")
                ->assertOk();
        }
    }

    public function test_every_resource_edit_page_renders_with_real_data(): void
    {
        $admin = $this->admin();
        foreach (self::RESOURCES as $slug => $model) {
            $record = $model::first();
            $this->assertNotNull($record, "nessun record seedato per {$slug}");
            $key = $record->getKey();
            $this->actingAs($admin)->get("/admin/{$slug}/{$key}/edit")
                ->assertOk();
        }
    }

    public function test_vendor_dashboard_renders_with_real_data(): void
    {
        $vendor = \App\Models\Vendor::first();
        $user = $vendor->user ?? User::factory()->create(['role' => 'vendor']);
        $user->update(['role' => 'vendor']);
        $vendor->update(['user_id' => $user->id, 'active' => true]);

        $this->actingAs($user->fresh())->get('/vendor')->assertOk();
    }
}
