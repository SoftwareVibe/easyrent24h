<?php

namespace App\Services\Pricing;

use App\Models\PriceCondition;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Replica del motore prezzi RenRoll (templines_get_active_conditions +
 * templines_get_range_conditions): ogni giorno del range viene classificato
 * dalla prima condizione di prezzo che lo copre e per cui esiste una riga di
 * listino del veicolo; i giorni residui vanno a tariffa base.
 */
class PriceResolver
{
    /**
     * @return array{total: float, days: int, breakdown: array<int, array{date: string, condition_id: int|null, price: float}>}
     */
    public function resolve(
        Vehicle $vehicle,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?int $pickupLocationId = null,
        ?int $dropoffLocationId = null,
    ): array {
        $days = $start->diffInDays($end) + 1; // booking_type "day": estremi inclusi

        $priceRows = $vehicle->prices()->get()->keyBy(
            fn ($row) => $row->price_condition_id ?? 0
        );
        $basePrice = $priceRows->has(0) ? (float) $priceRows[0]->price : null;

        $coverage = $this->activeConditions(
            $start,
            $end,
            $days,
            $pickupLocationId,
            $dropoffLocationId,
            $vehicle->vehicle_type_id,
        );

        $breakdown = [];
        $assigned = []; // dayIndex => condition_id
        $fixedCharged = [];
        $total = 0.0;

        // Assegnazione greedy nell'ordine (nome ASC) come get_terms() vanilla:
        // un giorno va alla prima condizione che lo copre e che ha un listino.
        foreach ($coverage as $conditionId => $cover) {
            if (! $priceRows->has($conditionId)) {
                continue;
            }
            foreach ($cover['days'] as $dayIndex) {
                if (! isset($assigned[$dayIndex])) {
                    $assigned[$dayIndex] = $conditionId;
                }
            }
        }

        for ($i = 1; $i <= $days; $i++) {
            $date = $start->addDays($i - 1)->toDateString();
            $conditionId = $assigned[$i] ?? null;

            if ($conditionId !== null) {
                $condition = $coverage[$conditionId]['condition'];
                $rowPrice = (float) $priceRows[$conditionId]->price;
                if ($condition->fixed_price) {
                    // forfait: addebitato una sola volta, non per giorno
                    $price = isset($fixedCharged[$conditionId]) ? 0.0 : $rowPrice;
                    $fixedCharged[$conditionId] = true;
                } else {
                    $price = $rowPrice;
                }
                $breakdown[] = ['date' => $date, 'condition_id' => $conditionId, 'price' => $price];
                $total += $price;
                continue;
            }

            if ($basePrice === null) {
                // Nessun listino applicabile: comportamento vanilla = totale nullo
                return ['total' => 0.0, 'days' => $days, 'breakdown' => []];
            }
            $breakdown[] = ['date' => $date, 'condition_id' => null, 'price' => $basePrice];
            $total += $basePrice;
        }

        return ['total' => round($total, 2), 'days' => $days, 'breakdown' => $breakdown];
    }

    /**
     * Per ogni condizione attiva, gli indici (1-based) dei giorni del range
     * che la soddisfano. Replica templines_get_active_conditions, incluso il
     * tie-break tra condizioni gemelle che differiscono solo per days_from.
     *
     * @return Collection<int, array{condition: PriceCondition, days: int[]}>
     */
    public function activeConditions(
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $days,
        ?int $pickupLocationId,
        ?int $dropoffLocationId,
        ?int $vehicleTypeId,
    ): Collection {
        $conditions = PriceCondition::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $coverage = collect();
        $dedup = [];

        foreach ($conditions as $condition) {
            $covered = [];

            for ($i = 1; $i <= $days; $i++) {
                $current = $start->addDays($i - 1);

                if (! $this->dayMatches($condition, $current, $days, $i, $pickupLocationId, $dropoffLocationId, $vehicleTypeId)) {
                    continue;
                }
                $covered[] = $i;
            }

            if (! $covered) {
                continue;
            }

            $coverage[$condition->id] = ['condition' => $condition, 'days' => $covered];

            // Condizioni con soli criteri di durata (days_from>1, no days_to):
            // tra gemelle con gli stessi altri criteri vince il days_from più alto.
            if ($days > 1 && $condition->days_from > 1 && ! $condition->days_to) {
                $hash = md5(json_encode([
                    $condition->days_first, $condition->fixed_price, $condition->weekdays,
                    $condition->month_days, $condition->months, $condition->years,
                    $condition->pickup_location_ids, $condition->dropoff_location_ids,
                    $condition->vehicle_type_ids,
                    $condition->date_from?->toDateString(), $condition->date_to?->toDateString(),
                ]));
                $dedup[$hash][$condition->id] = $condition->days_from;
            }
        }

        foreach ($dedup as $group) {
            if (count($group) > 1) {
                asort($group);
                array_pop($group); // resta solo il days_from più alto
                foreach (array_keys($group) as $loserId) {
                    $coverage->forget($loserId);
                }
            }
        }

        return $coverage;
    }

    private function dayMatches(
        PriceCondition $condition,
        CarbonImmutable $current,
        int $days,
        int $dayIndex,
        ?int $pickupLocationId,
        ?int $dropoffLocationId,
        ?int $vehicleTypeId,
    ): bool {
        if ($days < $condition->days_from) {
            return false;
        }
        if ($condition->weekdays && ! in_array((int) $current->format('w'), array_map('intval', $condition->weekdays), true)) {
            return false;
        }
        if ($condition->month_days && ! in_array((int) $current->format('d'), array_map('intval', $condition->month_days), true)) {
            return false;
        }
        if ($condition->months && ! in_array($current->format('m'), array_map(fn ($m) => str_pad($m, 2, '0', STR_PAD_LEFT), $condition->months), true)) {
            return false;
        }
        if ($condition->years && ! in_array($current->format('Y'), array_map('strval', $condition->years), true)) {
            return false;
        }
        if ($condition->pickup_location_ids && ! in_array($pickupLocationId, array_map('intval', $condition->pickup_location_ids), true)) {
            return false;
        }
        if ($condition->dropoff_location_ids && ! in_array($dropoffLocationId, array_map('intval', $condition->dropoff_location_ids), true)) {
            return false;
        }
        if ($condition->vehicle_type_ids && ! in_array($vehicleTypeId, array_map('intval', $condition->vehicle_type_ids), true)) {
            return false;
        }
        if ($condition->date_from && $current->lt($condition->date_from)) {
            return false;
        }
        if ($condition->date_to && $current->gt($condition->date_to)) {
            return false;
        }
        if ($condition->days_to && $days > $condition->days_to) {
            return false;
        }
        if ($condition->days_first && $dayIndex > $condition->days_first) {
            return false;
        }

        return true;
    }
}
