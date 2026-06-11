<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsCatalog;
use Tests\TestCase;

/**
 * Gate "lingua nei pannelli": il cambio lingua è disponibile nel menu
 * utente, viene salvato in memoria (profilo utente) e sopravvive a una
 * nuova sessione; l'interfaccia Filament si traduce di conseguenza.
 */
class AdminLocaleTest extends TestCase
{
    use BuildsCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
    }

    private function admin(string $locale = 'en'): User
    {
        return User::factory()->create(['role' => 'admin', 'locale' => $locale]);
    }

    public function test_switch_route_saves_locale_on_user(): void
    {
        $admin = $this->admin('en');

        $this->actingAs($admin)
            ->from('/admin')
            ->get('/locale/it')
            ->assertRedirect('/admin');

        $this->assertSame('it', $admin->fresh()->locale);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/locale/de')->assertNotFound();
        $this->assertSame('en', $admin->fresh()->locale);
    }

    public function test_guest_cannot_switch(): void
    {
        $this->get('/locale/it')->assertRedirect('/admin/login');
    }

    public function test_admin_ui_translates_to_italian(): void
    {
        $admin = $this->admin('it');

        // "Disconnetti" = logout nel pacchetto lingue italiano di Filament
        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Disconnetti');
    }

    public function test_admin_ui_translates_to_spanish(): void
    {
        $admin = $this->admin('es');

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Salir');
    }

    public function test_locale_persists_across_new_session(): void
    {
        $admin = $this->admin('en');

        // l'utente cambia lingua...
        $this->actingAs($admin)->get('/locale/it');

        // ...e in una NUOVA sessione (nessun dato di sessione) resta italiano
        $this->flushSession();
        $this->actingAs($admin->fresh())->get('/admin')
            ->assertOk()
            ->assertSee('Disconnetti');
    }

    public function test_user_menu_contains_language_switcher(): void
    {
        $admin = $this->admin('en');

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Italiano')
            ->assertSee('English')
            ->assertSee('Español')
            ->assertSee('/locale/it');
    }

    public function test_vendor_panel_is_localized_too(): void
    {
        $user = User::factory()->create(['role' => 'vendor', 'locale' => 'it']);
        $coupon = Coupon::create(['code' => 'lvendor', 'percent' => 5]);
        Vendor::create([
            'name' => 'Vendor Lingua', 'coupon_id' => $coupon->id,
            'commission_percent' => 5, 'user_id' => $user->id, 'active' => true,
        ]);

        $this->actingAs($user)->get('/vendor')
            ->assertOk()
            ->assertSee('Disconnetti')
            ->assertSee('Italiano');
    }

    public function test_switch_works_from_vendor_panel(): void
    {
        $user = User::factory()->create(['role' => 'vendor', 'locale' => 'en']);
        $coupon = Coupon::create(['code' => 'lvendor2', 'percent' => 5]);
        Vendor::create([
            'name' => 'Vendor Switch', 'coupon_id' => $coupon->id,
            'commission_percent' => 5, 'user_id' => $user->id, 'active' => true,
        ]);

        $this->actingAs($user)->from('/vendor')->get('/locale/es')->assertRedirect('/vendor');
        $this->assertSame('es', $user->fresh()->locale);
    }
}
