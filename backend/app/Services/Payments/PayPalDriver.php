<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Checkout via REST (Orders v2): create -> redirect/approve -> capture.
 * Sandbox o live in base a services.paypal.mode.
 */
class PayPalDriver implements PaymentDriver
{
    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.secret'))
            ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw();

        return $response->json('access_token');
    }

    public function initiate(Payment $payment): array
    {
        $response = Http::withToken($this->accessToken())
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $payment->id,
                    'custom_id' => $payment->order->number,
                    'amount' => [
                        'currency_code' => $payment->currency,
                        'value' => number_format($payment->amount, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'return_url' => config('app.frontend_url', 'http://localhost:5173').'/checkout/'.$payment->order->number.'?paypal=return',
                    'cancel_url' => config('app.frontend_url', 'http://localhost:5173').'/checkout/'.$payment->order->number.'?paypal=cancel',
                ],
            ])->throw();

        $payment->update(['provider_ref' => $response->json('id'), 'payload' => $response->json()]);

        $approve = collect($response->json('links'))->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'status' => 'requires_approval',
            'paypal_order_id' => $response->json('id'),
            'approve_url' => $approve,
        ];
    }

    public function confirm(Payment $payment, array $params = []): bool
    {
        $response = Http::withToken($this->accessToken())
            ->withBody('', 'application/json')
            ->post($this->baseUrl().'/v2/checkout/orders/'.$payment->provider_ref.'/capture');

        if (! $response->successful()) {
            return false;
        }

        return $response->json('status') === 'COMPLETED';
    }

    public function refund(Payment $payment): bool
    {
        $captureId = data_get($payment->payload, 'capture_id');
        if (! $captureId) {
            return false;
        }

        $response = Http::withToken($this->accessToken())
            ->withBody('', 'application/json')
            ->post($this->baseUrl().'/v2/payments/captures/'.$captureId.'/refund');

        if ($response->successful()) {
            $payment->update(['status' => Payment::STATUS_REFUNDED]);

            return true;
        }

        return false;
    }
}
