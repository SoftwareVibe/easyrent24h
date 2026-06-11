<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Vehicle;
use App\Services\Booking\QuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ex AJAX templines_calc_total: totale + fasce orarie selezionabili. */
class QuoteController extends Controller
{
    public function __invoke(Request $request, QuoteService $quoteService): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'pick_up' => ['nullable', 'integer', 'exists:locations,id'],
            'drop_off' => ['nullable', 'integer', 'exists:locations,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'min:0'],
            'time_start' => ['nullable', 'date_format:H:i'],
            'time_end' => ['nullable', 'date_format:H:i'],
        ]);

        $quote = $quoteService->quote(
            Vehicle::findOrFail($validated['vehicle_id']),
            CarbonImmutable::parse($validated['start']),
            CarbonImmutable::parse($validated['end']),
            isset($validated['pick_up']) ? Location::find($validated['pick_up']) : null,
            isset($validated['drop_off']) ? Location::find($validated['drop_off']) : null,
            (int) ($validated['quantity'] ?? 1),
            $validated['extras'] ?? [],
            $validated['time_start'] ?? null,
            $validated['time_end'] ?? null,
        );

        return response()->json($quote);
    }
}
