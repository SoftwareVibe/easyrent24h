<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Stripe\StripeClient;

/**
 * Stripe Payment Intents: il frontend conferma con Stripe.js usando il
 * client_secret; la conferma definitiva arriva dal webhook
 * payment_intent.succeeded (vedi StripeWebhookController).
 */
class StripeDriver implements PaymentDriver
{
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    public function initiate(Payment $payment): array
    {
        $intent = $this->client->paymentIntents->create([
            'amount' => (int) round($payment->amount * 100),
            'currency' => strtolower($payment->currency),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'payment_id' => $payment->id,
                'order_number' => $payment->order->number,
                'type' => $payment->type,
            ],
        ]);

        $payment->update(['provider_ref' => $intent->id]);

        return [
            'status' => 'requires_action',
            'client_secret' => $intent->client_secret,
            'publishable_key' => config('services.stripe.key'),
        ];
    }

    public function confirm(Payment $payment, array $params = []): bool
    {
        $intent = $this->client->paymentIntents->retrieve($payment->provider_ref);

        return $intent->status === 'succeeded';
    }

    public function refund(Payment $payment): bool
    {
        $this->client->refunds->create(['payment_intent' => $payment->provider_ref]);
        $payment->update(['status' => Payment::STATUS_REFUNDED]);

        return true;
    }
}
