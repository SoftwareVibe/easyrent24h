<?php

namespace App\Services\Booking;

use App\Models\Extra;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Services\Availability\AvailabilityEngine;
use App\Services\Pricing\ExtraPriceCalculator;
use App\Services\Pricing\PriceResolver;
use Carbon\CarbonImmutable;

/**
 * Orchestratore del preventivo (ex templines_ajax_calc_total):
 * vincoli date -> regola "giorno in meno" -> prezzo -> extra -> fasce orarie.
 */
class QuoteService
{
    public function __construct(
        private PriceResolver $priceResolver,
        private ExtraPriceCalculator $extraCalculator,
        private AvailabilityEngine $availability,
    ) {
    }

    /**
     * @param array<int, int> $extras extra_id => qty
     */
    public function quote(
        Vehicle $vehicle,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?Location $pickup = null,
        ?Location $dropoff = null,
        int $quantity = 1,
        array $extras = [],
        ?string $timeStart = null,
        ?string $timeEnd = null,
    ): array {
        $quantity = max(1, $quantity);
        $days = (int) $start->diffInDays($end) + 1;

        // Vincoli globali: giorni della settimana ammessi e festività
        $allowedWeekdays = (array) Setting::get('pickup_dropoff_days', [1, 2, 3, 4, 5, 6, 7]);
        $holidays = (array) Setting::get('holidays', []);

        if (! in_array((int) $start->format('N'), array_map('intval', $allowedWeekdays), true)
            || in_array($start->toDateString(), $holidays, true)) {
            return $this->error(__(':date is not available for pick up', ['date' => $start->format('d.m.Y')]), $days);
        }
        if (! in_array((int) $end->format('N'), array_map('intval', $allowedWeekdays), true)
            || in_array($end->toDateString(), $holidays, true)) {
            return $this->error(__(':date is not available for drop off', ['date' => $end->format('d.m.Y')]), $days);
        }

        // Min/max giorni: override per veicolo, poi default globale
        $minDays = $vehicle->min_days ?? (int) Setting::get('minimum_days', 1);
        $maxDays = $vehicle->max_days ?? Setting::get('maximum_days');
        if ($days < $minDays) {
            return $this->error(__('Minimum rental is :n days', ['n' => $minDays]), $days);
        }
        if ($maxDays && $days > (int) $maxDays) {
            return $this->error(__('Maximum rental is :n days', ['n' => $maxDays]), $days);
        }

        // Regola "giorno in meno": riconsegna mattutina (<= soglia) su noleggio
        // multi-giorno => l'ultimo giorno non si paga (ex IsOrarioInferioreAllaConsegna).
        $billableEnd = $end;
        $billableDays = $days;
        if ($this->earlyReturnApplies($start, $end, $timeEnd)) {
            $billableEnd = $end->subDay();
            $billableDays = $days - 1;
        }

        $price = $this->priceResolver->resolve($vehicle, $start, $billableEnd, $pickup?->id, $dropoff?->id);

        if ($vehicle->price_on_request) {
            $availability = $this->availability->check($vehicle, $start, $end, $pickup, $dropoff, $quantity, $timeStart, $timeEnd);

            return [
                'available' => $availability['available'],
                'price_on_request' => true,
                'days' => $billableDays,
                'total' => null,
                'message' => $availability['message'],
                'start_slots' => $availability['start_slots'],
                'end_slots' => $availability['end_slots'],
                'extras' => $this->extrasCatalog($vehicle, $start, $billableEnd, $pickup, $dropoff),
            ];
        }

        if ($price['total'] <= 0) {
            return $this->error(__('Dates are unavailable'), $billableDays);
        }

        $availability = $this->availability->check($vehicle, $start, $end, $pickup, $dropoff, $quantity, $timeStart, $timeEnd);
        if (! $availability['available']) {
            return $this->error($availability['message'] ?? __('Dates are unavailable'), $billableDays);
        }

        $extrasTotal = 0.0;
        $extraLines = [];
        if ($extras) {
            $vehicleExtras = $vehicle->extras()->get()->keyBy('id');
            foreach ($extras as $extraId => $qty) {
                $extra = $vehicleExtras->get((int) $extraId);
                if (! $extra || (int) $qty < 1) {
                    continue;
                }
                $lineTotal = $this->extraCalculator->total($extra, (int) $qty, $start, $billableEnd, $pickup?->id, $dropoff?->id);
                $extrasTotal += $lineTotal;
                $extraLines[] = [
                    'extra_id' => $extra->id,
                    'name' => $extra->name,
                    'qty' => min((int) $qty, $extra->max_qty),
                    'total' => $lineTotal,
                ];
            }
        }

        return [
            'available' => true,
            'price_on_request' => false,
            'days' => $billableDays,
            'price' => round($price['total'] * $quantity, 2),
            'price_per_day' => $billableDays ? round($price['total'] / $billableDays, 2) : 0,
            'extras_total' => round($extrasTotal * $quantity, 2),
            'total' => round(($price['total'] + $extrasTotal) * $quantity, 2),
            'breakdown' => $price['breakdown'],
            'extra_lines' => $extraLines,
            'message' => null,
            'start_slots' => $availability['start_slots'],
            'end_slots' => $availability['end_slots'],
            'extras' => $this->extrasCatalog($vehicle, $start, $billableEnd, $pickup, $dropoff),
        ];
    }

    public function earlyReturnApplies(CarbonImmutable $start, CarbonImmutable $end, ?string $timeEnd): bool
    {
        if ($start->isSameDay($end)) {
            return false;
        }
        $threshold = Setting::get('early_return_threshold', '09:30');

        // Come nel sito attuale: senza orario scelto il preventivo parte
        // dall'ipotesi di riconsegna mattutina (giorno in meno).
        return $timeEnd === null || $timeEnd <= $threshold;
    }

    /** Prezzo unitario effettivo di ogni extra del veicolo (per il popup). */
    private function extrasCatalog(Vehicle $vehicle, CarbonImmutable $start, CarbonImmutable $end, ?Location $pickup, ?Location $dropoff): array
    {
        return $vehicle->extras()->get()->map(fn (Extra $extra) => [
            'id' => $extra->id,
            'name' => $extra->name,
            'type' => $extra->type,
            'max_qty' => $extra->max_qty,
            'always_included' => $extra->always_included,
            'list_price' => (float) $extra->price,
            'effective_price' => $this->extraCalculator->total($extra, 1, $start, $end, $pickup?->id, $dropoff?->id),
        ])->values()->all();
    }

    private function error(string $message, int $days): array
    {
        return [
            'available' => false,
            'price_on_request' => false,
            'days' => $days,
            'total' => null,
            'message' => $message,
            'start_slots' => [],
            'end_slots' => [],
            'extras' => [],
        ];
    }
}
