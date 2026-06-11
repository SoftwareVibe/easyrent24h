<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsCatalog;
use Tests\TestCase;

class OrderMailTest extends TestCase
{
    use BuildsCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
    }

    private function makeOrder(string $locale): Order
    {
        $location = $this->makeLocation('Agerola');
        $vehicle = $this->makeVehicle(50, 1);

        $order = Order::create([
            'number' => Order::generateNumber(),
            'status' => 'deposit_paid',
            'customer_name' => 'Mario Rossi',
            'customer_email' => 'mario@example.com',
            'locale' => $locale,
            'subtotal' => 150,
            'total' => 150,
            'deposit_amount' => 37.5,
            'paid_total' => 37.5,
        ]);
        $order->bookings()->create([
            'vehicle_id' => $vehicle->id,
            'date_start' => '2026-07-10',
            'date_end' => '2026-07-12',
            'time_start' => '10:00',
            'time_end' => '10:00',
            'pickup_location_id' => $location->id,
            'status' => 'confirmed',
            'days' => 3,
            'price' => 150,
        ]);

        return $order->fresh('bookings');
    }

    public function test_confirmation_email_renders_in_three_languages(): void
    {
        $expectations = [
            'en' => ['subject' => 'Booking confirmation', 'body' => ['has been confirmed', 'Pick up', 'Balance due at pick up']],
            'it' => ['subject' => 'Conferma prenotazione', 'body' => ['è stata confermata', 'Ritiro', 'Saldo al ritiro']],
            'es' => ['subject' => 'Confirmación de reserva', 'body' => ['ha sido confirmada', 'Recogida', 'Saldo pendiente']],
        ];

        foreach ($expectations as $locale => $expected) {
            $order = $this->makeOrder($locale);

            $previous = app()->getLocale();
            app()->setLocale($locale);
            $mailable = new OrderConfirmationMail($order);
            $subject = $mailable->envelope()->subject;
            $body = $mailable->render();
            app()->setLocale($previous);

            $this->assertStringContainsString($expected['subject'], $subject, "subject errato [$locale]");
            $this->assertStringContainsString($order->number, $body, "numero ordine mancante [$locale]");
            foreach ($expected['body'] as $string) {
                $this->assertStringContainsString($string, $body, "stringa '$string' mancante [$locale]");
            }
        }
    }
}
