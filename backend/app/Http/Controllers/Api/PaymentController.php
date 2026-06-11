<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Booking\CouponService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments)
    {
    }

    /** Metodi di pagamento disponibili (per il checkout). */
    public function config(): JsonResponse
    {
        return response()->json(['providers' => $this->payments->enabledProviders()]);
    }

    /** Riepilogo ordine per la pagina di checkout. */
    public function show(string $number): JsonResponse
    {
        $order = Order::with(['bookings.vehicle:id,name', 'bookings.pickupLocation:id,name', 'bookings.dropoffLocation:id,name'])
            ->where('number', $number)->firstOrFail();

        return response()->json([
            'number' => $order->number,
            'status' => $order->status,
            'customer_name' => $order->customer_name,
            'subtotal' => $order->subtotal,
            'extras_total' => $order->extras_total,
            'discount_total' => $order->discount_total,
            'total' => $order->total,
            'deposit_amount' => $order->deposit_amount,
            'paid_total' => $order->paid_total,
            'coupon_code' => $order->coupon_code,
            'bookings' => $order->bookings->map(fn ($b) => [
                'vehicle' => $b->vehicle?->name,
                'date_start' => $b->date_start->toDateString(),
                'date_end' => $b->date_end->toDateString(),
                'time_start' => $b->time_start ? substr($b->time_start, 0, 5) : null,
                'time_end' => $b->time_end ? substr($b->time_end, 0, 5) : null,
                'pickup' => $b->pickupLocation?->name,
                'dropoff' => $b->dropoffLocation?->name,
                'days' => $b->days,
                'extras' => $b->extras,
            ]),
        ]);
    }

    /** Avvia un pagamento: acconto, saldo o totale. */
    public function pay(Request $request, string $number): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:stripe,paypal,offline'],
            'type' => ['required', 'in:deposit,balance,full'],
        ]);

        $order = Order::where('number', $number)->firstOrFail();
        $result = $this->payments->initiate($order, $validated['provider'], $validated['type']);

        return response()->json($result + ['order_status' => $order->fresh()->status]);
    }

    /** Conferma/cattura (return PayPal o polling Stripe senza webhook). */
    public function confirm(Request $request, string $number): JsonResponse
    {
        $validated = $request->validate(['payment_id' => ['required', 'integer']]);

        $order = Order::where('number', $number)->firstOrFail();
        $payment = $order->payments()->findOrFail($validated['payment_id']);
        $ok = $this->payments->confirm($payment, $request->all());

        return response()->json([
            'confirmed' => $ok,
            'order_status' => $order->fresh()->status,
        ]);
    }

    /** Annulla l'ordine con rimborso: libera il calendario. */
    public function cancel(string $number): JsonResponse
    {
        $order = Order::where('number', $number)->firstOrFail();
        $this->payments->cancel($order);

        return response()->json(['order_status' => $order->fresh()->status]);
    }

    /** Validazione coupon per il popup/checkout. */
    public function coupon(Request $request, CouponService $coupons): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'pick_up' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $pickup = isset($validated['pick_up']) ? \App\Models\Location::find($validated['pick_up']) : null;

        return response()->json($coupons->validate($validated['code'], $pickup));
    }
}
