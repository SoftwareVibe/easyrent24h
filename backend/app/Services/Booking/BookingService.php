<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creazione prenotazione (ex templines_ajax_book + templines_add_booking):
 * rivalida tutto server-side dentro una transazione con lock per veicolo,
 * eliminando l'overbooking concorrente del sito attuale.
 */
class BookingService
{
    public function __construct(
        private QuoteService $quoteService,
        private CouponService $couponService,
    ) {
    }

    /**
     * @param array<int, int> $extras extra_id => qty
     */
    public function book(
        Vehicle $vehicle,
        CarbonImmutable $start,
        CarbonImmutable $end,
        Location $pickup,
        ?Location $dropoff,
        string $timeStart,
        string $timeEnd,
        int $quantity,
        array $extras,
        array $customer,
        string $locale = 'en',
    ): Order {
        // Validazioni località (ex templines_ajax_book)
        if (! $vehicle->pickupLocations()->whereKey($pickup->id)->exists()) {
            throw ValidationException::withMessages(['pick_up' => __('Pick up location is not available')]);
        }
        if ($dropoff && $vehicle->dropoffLocations()->exists()
            && ! $vehicle->dropoffLocations()->whereKey($dropoff->id)->exists()) {
            throw ValidationException::withMessages(['drop_off' => __('Drop off location is not available')]);
        }
        if ($start->isSameDay($end) && $timeStart >= $timeEnd) {
            throw ValidationException::withMessages(['time' => __('The start time must be earlier than the end time.')]);
        }

        return DB::transaction(function () use ($vehicle, $start, $end, $pickup, $dropoff, $timeStart, $timeEnd, $quantity, $extras, $customer, $locale) {
            // Lock pessimistico sul veicolo: due checkout simultanei sullo
            // stesso mezzo vengono serializzati.
            Vehicle::lockForUpdate()->findOrFail($vehicle->id);

            $quote = $this->quoteService->quote(
                $vehicle, $start, $end, $pickup, $dropoff, $quantity, $extras, $timeStart, $timeEnd,
            );

            if (! $quote['available'] || $quote['price_on_request']) {
                throw ValidationException::withMessages(['dates' => $quote['message'] ?? __('Dates are unavailable')]);
            }

            // Coupon (con eccezioni coupon-hub, ex bartoloparcheggio/Agerola)
            $couponCode = $customer['coupon_code'] ?? null;
            $discount = 0.0;
            if ($couponCode) {
                $coupon = $this->couponService->validate($couponCode, $pickup);
                if (! $coupon['valid']) {
                    throw ValidationException::withMessages(['coupon_code' => $coupon['message'] ?? __('Coupon not valid')]);
                }
                $discount = round($quote['total'] * $coupon['percent'] / 100, 2);
            }

            $total = round($quote['total'] - $discount, 2);
            $depositPercent = (float) Setting::get('deposit_percent', 0);

            $order = Order::create([
                'number' => Order::generateNumber(),
                'status' => 'pending',
                'customer_name' => $customer['name'] ?? null,
                'customer_email' => $customer['email'] ?? null,
                'customer_phone' => $customer['phone'] ?? null,
                'locale' => $locale,
                'subtotal' => $quote['price'],
                'extras_total' => $quote['extras_total'],
                'discount_total' => $discount,
                'total' => $total,
                'deposit_amount' => $depositPercent ? round($total * $depositPercent / 100, 2) : null,
                'coupon_code' => $couponCode,
            ]);

            $order->bookings()->create([
                'vehicle_id' => $vehicle->id,
                'date_start' => $start->toDateString(),
                'date_end' => $end->toDateString(),
                'time_start' => $timeStart,
                'time_end' => $timeEnd,
                'pickup_location_id' => $pickup->id,
                'dropoff_location_id' => $dropoff?->id,
                'quantity' => $quantity,
                'status' => Booking::STATUS_PENDING,
                'days' => $quote['days'],
                'price' => $quote['price'],
                'extras_total' => $quote['extras_total'],
                'extras' => $quote['extra_lines'],
            ]);

            return $order;
        });
    }

    /** Cambio stato ordine: libera o occupa il calendario (ex add/remove_booking). */
    public function syncStatus(Order $order, string $status): void
    {
        $order->update(['status' => $status]);
        $bookingStatus = match ($status) {
            'confirmed' => Booking::STATUS_CONFIRMED,
            'cancelled', 'refunded' => Booking::STATUS_CANCELLED,
            default => Booking::STATUS_PENDING,
        };
        $order->bookings()->update(['status' => $bookingStatus]);
    }
}
