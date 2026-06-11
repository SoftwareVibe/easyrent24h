<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Vehicle;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ex AJAX templines_book: crea ordine + prenotazione (stato pending). */
class BookingController extends Controller
{
    public function store(Request $request, BookingService $bookingService): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'pick_up' => ['required', 'integer', 'exists:locations,id'],
            'drop_off' => ['nullable', 'integer', 'exists:locations,id'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'min:0'],
            'customer.name' => ['required', 'string', 'max:190'],
            'customer.email' => ['required', 'email', 'max:190'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer.coupon_code' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'in:en,it,es'],
        ]);

        $order = $bookingService->book(
            Vehicle::findOrFail($validated['vehicle_id']),
            CarbonImmutable::parse($validated['start']),
            CarbonImmutable::parse($validated['end']),
            Location::findOrFail($validated['pick_up']),
            isset($validated['drop_off']) ? Location::find($validated['drop_off']) : null,
            $validated['time_start'],
            $validated['time_end'],
            (int) ($validated['quantity'] ?? 1),
            $validated['extras'] ?? [],
            $validated['customer'],
            $validated['locale'] ?? 'en',
        );

        return response()->json([
            'order_number' => $order->number,
            'status' => $order->status,
            'total' => $order->total,
            'deposit_amount' => $order->deposit_amount,
        ], 201);
    }
}
