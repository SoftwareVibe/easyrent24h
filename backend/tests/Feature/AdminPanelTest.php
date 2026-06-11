<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsCatalog;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use BuildsCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function vendorUser(): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $coupon = Coupon::create(['code' => 'vendorcoupon', 'percent' => 5]);
        $vendor = Vendor::create([
            'name' => 'Vendor Test',
            'coupon_id' => $coupon->id,
            'commission_percent' => 10,
            'user_id' => $user->id,
            'active' => true,
        ]);

        return [$user, $vendor, $coupon];
    }

    public function test_admin_can_open_main_resources(): void
    {
        $this->makeVehicle();
        $admin = $this->admin();

        foreach (['/admin', '/admin/vehicles', '/admin/bookings', '/admin/orders', '/admin/locations', '/admin/price-conditions', '/admin/coupons', '/admin/vendors'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_vehicle_edit_page_with_price_relation_renders(): void
    {
        $vehicle = $this->makeVehicle();
        $this->actingAs($this->admin())
            ->get("/admin/vehicles/{$vehicle->id}/edit")
            ->assertOk();
    }

    public function test_vendor_cannot_access_admin_panel(): void
    {
        [$user] = $this->vendorUser();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_vendor_can_access_vendor_panel_admin_cannot(): void
    {
        [$user] = $this->vendorUser();

        $this->actingAs($user)->get('/vendor')->assertOk();
        $this->actingAs($this->admin())->get('/vendor')->assertForbidden();
    }

    public function test_vendor_orders_scoped_to_own_coupon(): void
    {
        [, $vendor, $coupon] = $this->vendorUser();

        Order::create(['number' => 'ER-1', 'status' => 'paid', 'total' => 100, 'coupon_code' => $coupon->code]);
        Order::create(['number' => 'ER-2', 'status' => 'deposit_paid', 'total' => 200, 'coupon_code' => $coupon->code]);
        Order::create(['number' => 'ER-3', 'status' => 'paid', 'total' => 999, 'coupon_code' => 'altro']);
        Order::create(['number' => 'ER-4', 'status' => 'pending', 'total' => 50, 'coupon_code' => $coupon->code]);

        $this->assertSame(2, $vendor->orders()->count());
        // 10% di (100+200)
        $this->assertSame(30.0, $vendor->commissionAccrued());

        VendorPayment::create(['vendor_id' => $vendor->id, 'amount' => 12.5, 'paid_at' => '2026-06-01']);
        $this->assertSame(12.5, $vendor->commissionPaid());
    }

    public function test_coupon_qr_code_is_served(): void
    {
        Coupon::create(['code' => 'qrtest', 'percent' => 5]);

        $response = $this->get('/qr/coupon/qrtest.svg');
        $response->assertOk();
        $this->assertStringContainsString('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }
}
