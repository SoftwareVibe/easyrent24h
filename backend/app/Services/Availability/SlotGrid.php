<?php

namespace App\Services\Availability;

use Carbon\CarbonImmutable;

/**
 * Griglia di orari a step fissi (ex generaOrariIntermedi): "08:00".."20:00"
 * ogni 30 minuti. Tutti gli orari sono stringhe H:i.
 */
class SlotGrid
{
    public function __construct(
        public readonly string $dayStart,
        public readonly string $dayEnd,
        public readonly int $slotMinutes,
    ) {
    }

    /** @return string[] */
    public function slots(?string $from = null, ?string $to = null, array $exclude = []): array
    {
        $from ??= $this->dayStart;
        $to ??= $this->dayEnd;

        $current = $this->toMinutes($from);
        $end = $this->toMinutes($to);
        $result = [];

        while ($current <= $end) {
            $time = $this->toTime($current);
            if (! in_array($time, $exclude, true)) {
                $result[] = $time;
            }
            $current += $this->slotMinutes;
        }

        return $result;
    }

    /** Slot successivi a "adesso + lead" (ex getDopoOrarioAttuale). */
    public function afterNow(array $slots, CarbonImmutable $now, int $leadMinutes): array
    {
        $threshold = $now->addMinutes($leadMinutes);
        $thresholdMinutes = $threshold->hour * 60 + $threshold->minute;

        return array_values(array_filter(
            $slots,
            fn (string $slot) => $this->toMinutes($slot) > $thresholdMinutes,
        ));
    }

    public function toMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);

        return (int) $h * 60 + (int) $m;
    }

    public function toTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function next(string $time): string
    {
        return $this->toTime($this->toMinutes($time) + $this->slotMinutes);
    }
}
