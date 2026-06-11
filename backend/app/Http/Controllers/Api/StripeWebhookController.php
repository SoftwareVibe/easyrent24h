<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        if ($secret) {
            try {
                $event = Webhook::constructEvent(
                    $request->getContent(),
                    $request->header('Stripe-Signature', ''),
                    $secret,
                );
            } catch (\Throwable) {
                return response()->json(['error' => 'invalid signature'], 400);
            }
            $payload = $event->toArray();
        } else {
            // sviluppo locale senza firma (stripe listen --forward-to ...)
            $payload = $request->json()->all();
        }

        $type = $payload['type'] ?? '';
        $intentId = data_get($payload, 'data.object.id');

        if ($intentId && in_array($type, ['payment_intent.succeeded', 'payment_intent.payment_failed'], true)) {
            $payment = Payment::where('provider', 'stripe')->where('provider_ref', $intentId)->first();
            if ($payment) {
                if ($type === 'payment_intent.succeeded') {
                    $payments->markSucceeded($payment);
                } else {
                    $payment->update(['status' => Payment::STATUS_FAILED]);
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
