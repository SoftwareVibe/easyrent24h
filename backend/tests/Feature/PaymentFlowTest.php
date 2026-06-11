<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\Booking;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsCatalog;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use BuildsCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
        Mail::fake();
    }

    private function createOrder(array $overrides = []): string
    {
        $location = $this->makeLocation('Agerola');
        $vehicle = $this->makeVehicle(50, 1);
        $vehicle->pickupLocations()->attach($location);

        $response = $this->postJson('/api/bookings', array_replace_recursive([
            'vehicle_id' => $vehicle->id,
            'start' => '2026-07-10',
            'end' => '2026-07-12',
            'pick_up' => $location->id,
            'time_start' => '10:00',
            'time_end' => '10:00',
            'customer' => ['name' => 'Mario Rossi', 'email' => 'mario@example.com'],
        ], $overrides));

        $response->assertCreated();

        return $response->json('order_number');
    }

    public function test_deposit_payment_confirms_booking_and_sends_email(): void
    {
        $number = $this->createOrder();

        // Acconto 25% di 150 = 37.50
        $pay = $this->postJson("/api/orders/{$number}/pay", ['provider' => 'offline', 'type' => 'deposit']);
        $pay->assertOk()->assertJsonPath('order_status', 'deposit_paid');

        $order = Order::where('number', $number)->first();
        $this->assertSame(37.5, (float) $order->paid_total);
        $this->assertSame(Booking::STATUS_CONFIRMED, $order->bookings()->first()->status);

        Mail::assertSent(OrderConfirmationMail::class, 1);

        // Saldo: ordine pagato per intero, nessuna seconda email
        $balance = $this->postJson("/api/orders/{$number}/pay", ['provider' => 'offline', 'type' => 'balance']);
        $balance->assertOk()->assertJsonPath('order_status', 'paid');
        $this->assertSame(150.0, (float) $order->fresh()->paid_total);
        Mail::assertSent(OrderConfirmationMail::class, 1);
    }

    public function test_full_payment_marks_order_paid(): void
    {
        $number = $this->createOrder();

        $this->postJson("/api/orders/{$number}/pay", ['provider' => 'offline', 'type' => 'full'])
            ->assertOk()
            ->assertJsonPath('order_status', 'paid');
    }

    public function test_cancellation_refunds_and_frees_dates(): void
    {
        $location = $this->makeLocation('Agerola');
        $vehicle = $this->makeVehicle(50, 1);
        $vehicle->pickupLocations()->attach($location);

        $payload = [
            'vehicle_id' => $vehicle->id,
            'start' => '2026-07-10',
            'end' => '2026-07-12',
            'pick_up' => $location->id,
            'time_start' => '10:00',
            'time_end' => '10:00',
            'customer' => ['name' => 'Mario Rossi', 'email' => 'mario@example.com'],
        ];

        $number = $this->postJson('/api/bookings', $payload)->assertCreated()->json('order_number');
        $this->postJson("/api/orders/{$number}/pay", ['provider' => 'offline', 'type' => 'deposit'])->assertOk();

        // Calendario occupato: stessa prenotazione respinta
        $this->postJson('/api/bookings', $payload)->assertUnprocessable();

        // Annullo con rimborso: date di nuovo libere
        $this->postJson("/api/orders/{$number}/cancel")
            ->assertOk()
            ->assertJsonPath('order_status', 'refunded');

        $this->postJson('/api/bookings', $payload)->assertCreated();
    }

    public function test_order_summary_endpoint(): void
    {
        $number = $this->createOrder();

        $response = $this->getJson("/api/orders/{$number}")
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('bookings.0.pickup', 'Agerola');

        $this->assertSame(150.0, (float) $response->json('total'));
        $this->assertSame(37.5, (float) $response->json('deposit_amount'));
    }
}
