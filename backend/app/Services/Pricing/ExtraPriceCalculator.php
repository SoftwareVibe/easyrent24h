<?php

namespace App\Services\Pricing;

use App\Models\Extra;
use Carbon\CarbonImmutable;

/**
 * Replica di templines_get_extra_total: extra "total" = forfait una tantum,
 * extra "day" = per giorno; entrambi con eventuale prezzo condizionale
 * (es. "Price Delivery" 25€ base ma 0–20€ a seconda della località).
 */
class ExtraPriceCalculator
{
    public function __construct(private PriceResolver $priceResolver)
    {
    }

    public function total(
        Extra $extra,
        int $qty,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?int $pickupLocationId = null,
        ?int $dropoffLocationId = null,
    ): float {
        if ($qty < 1) {
            return 0.0;
        }
        $qty = min($qty, max(1, (int) $extra->max_qty));
        $days = (int) $start->diffInDays($end) + 1;

        $overrides = $extra->conditionalPrices()->get()->keyBy('price_condition_id');

        if ($overrides->isEmpty()) {
            return round($this->flat($extra, $qty, $days), 2);
        }

        $coverage = $this->priceResolver->activeConditions(
            $start, $end, $days, $pickupLocationId, $dropoffLocationId, null
        );

        $selectedDays = range(1, $days);
        $total = 0.0;
        $matchedTotalType = false;

        foreach ($coverage as $conditionId => $cover) {
            if (! $overrides->has($conditionId)) {
                continue;
            }
            $price = (float) $overrides[$conditionId]->price;

            if ($extra->type === 'day') {
                $coveredSelected = array_intersect($cover['days'], $selectedDays);
                $total += $price * $qty * count($coveredSelected);
                $selectedDays = array_diff($selectedDays, $cover['days']);
                if (! $selectedDays) {
                    break;
                }
            } else {
                $total += $price * $qty;
                $matchedTotalType = true;
                break;
            }
        }

        if ($selectedDays !== [] || $extra->type === 'day') {
            if ($extra->type === 'day') {
                $total += (float) $extra->price * $qty * count($selectedDays);
            } elseif (! $matchedTotalType) {
                $total += (float) $extra->price * $qty;
            }
        } elseif ($extra->type === 'total' && ! $matchedTotalType) {
            $total += (float) $extra->price * $qty;
        }

        return round($total, 2);
    }

    private function flat(Extra $extra, int $qty, int $days): float
    {
        return $extra->type === 'day'
            ? (float) $extra->price * $qty * $days
            : (float) $extra->price * $qty;
    }
}
