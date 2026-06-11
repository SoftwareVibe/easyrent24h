<?php

namespace App\Services\Availability;

use App\Models\Booking;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

/**
 * Replica del motore di disponibilità RenRoll custom:
 * occupazione per slot di 30' per veicolo/giorno confrontata con lo stock
 * (templines_check_dates + templines_check_cart_availability + filtri fasce
 * di templines_ajax_calc_total), calcolata a runtime dalla tabella bookings.
 */
class AvailabilityEngine
{
    private SlotGrid $grid;
    private int $leadMinutes;
    private int $cleaningDays;
    private string $timezone;

    public function __construct()
    {
        $this->grid = new SlotGrid(
            Setting::get('day_start', '08:00'),
            Setting::get('day_end', '20:00'),
            (int) Setting::get('slot_minutes', 30),
        );
        $this->leadMinutes = (int) Setting::get('lead_minutes', 30);
        $this->cleaningDays = (int) Setting::get('cleaning_days', 0);
        $this->timezone = Setting::get('timezone', 'Europe/Rome');
    }

    /**
     * Verifica range + slot proponibili per ritiro e riconsegna.
     *
     * @return array{available: bool, message: string|null, start_slots: string[], end_slots: string[]}
     */
    public function check(
        Vehicle $vehicle,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?Location $pickup = null,
        ?Location $dropoff = null,
        int $quantity = 1,
        ?string $timeStart = null,
        ?string $timeEnd = null,
    ): array {
        $now = CarbonImmutable::now($this->timezone);

        // Eccezione veicoli non noleggiabili in giornata (ex checkStessoGiornoVeicoliEccezione)
        if ($vehicle->no_same_day && $start->isSameDay($now)) {
            return $this->unavailable();
        }

        if ($end->lt($start) || $start->lt($now->startOfDay())) {
            return $this->unavailable();
        }

        $effectiveEnd = $this->cleaningDays ? $end->addDays($this->cleaningDays) : $end;
        $bookings = $this->activeBookings($vehicle, $start, $effectiveEnd);

        // 1) Blocchi manuali: rendono indisponibile l'intero range sovrapposto
        foreach ($bookings as $booking) {
            if ($booking->status === Booking::STATUS_BLOCK) {
                return $this->unavailable();
            }
        }

        // 2) Giorni interni saturi (ex templines_check_dates: day>start AND day<end)
        $occupancy = $this->occupancy($bookings);
        $stock = max(1, (int) $vehicle->stock);

        for ($day = $start->addDay(); $day->lt($effectiveEnd); $day = $day->addDay()) {
            $key = $day->toDateString();
            $dailyCount = $this->dailyCount($bookings, $day);
            if ($dailyCount + $quantity > $stock) {
                return $this->unavailable();
            }
            unset($key);
        }

        // 3) Slot con occupazione < stock per il giorno di ritiro e riconsegna
        //    (ex getFasciaOrariaCorretta: la quantità richiesta deve trovare posto)
        $startSlots = $this->freeSlots($occupancy, $start->toDateString(), $stock, $quantity);
        $endSlots = $this->freeSlots($occupancy, $end->toDateString(), $stock, $quantity);

        // 4) Esclusioni hub: slot già impegnati da ritiri/consegne nello stesso hub
        //    (ex getFasceDaEscludere su renroll_order.pickupLocation)
        $startExcluded = $this->hubBusySlots($pickup, $start, $vehicle);
        $endExcluded = $this->hubBusySlots($dropoff, $end, $vehicle);
        $startSlots = array_values(array_diff($startSlots, $startExcluded));
        $endSlots = array_values(array_diff($endSlots, $endExcluded));

        // 5) Preavviso minimo se il giorno è oggi (ex getDopoOrarioAttuale, +30')
        if ($start->isSameDay($now)) {
            $startSlots = $this->grid->afterNow($startSlots, $now, $this->leadMinutes);
        }
        if ($end->isSameDay($now)) {
            $endSlots = $this->grid->afterNow($endSlots, $now, $this->leadMinutes);
        }

        // 6) Coerenza same-day / contiguità multi-day
        if ($start->isSameDay($end)) {
            [$startSlots, $endSlots] = $this->sameDayConstrain($startSlots, $endSlots, $timeStart);
        } else {
            $startSlots = $this->contiguousTail($startSlots, $startExcluded);
            $endSlots = $this->contiguousHead($endSlots, $endExcluded);
        }

        // 7) Finestre orarie per località (ex fascia*PerLuoghiSpecifici)
        $startSlots = $this->intersectLocationWindow($startSlots, $pickup);
        $endSlots = $this->intersectLocationWindow($endSlots, $dropoff);

        if (! $startSlots || ! $endSlots) {
            return $this->unavailable();
        }

        // 8) Validazione finale con orari scelti: la richiesta non deve saturare
        //    nessuno slot oltre lo stock (ex checkHoursCnt con richiesta inclusa)
        if ($timeStart && $timeEnd) {
            if (! in_array($timeStart, $startSlots, true) || ! in_array($timeEnd, $endSlots, true)) {
                return $this->unavailable($startSlots, $endSlots);
            }
            $requested = $this->expandBooking($start, $end, $timeStart, $timeEnd, $quantity);
            foreach ($requested as $day => $slots) {
                foreach ($slots as $slot => $qty) {
                    if (($occupancy[$day][$slot] ?? 0) + $qty > $stock) {
                        return $this->unavailable($startSlots, $endSlots);
                    }
                }
            }
        }

        return [
            'available' => true,
            'message' => null,
            'start_slots' => $startSlots,
            'end_slots' => $endSlots,
        ];
    }

    public function grid(): SlotGrid
    {
        return $this->grid;
    }

    private function unavailable(array $startSlots = [], array $endSlots = []): array
    {
        return [
            'available' => false,
            'message' => __('Dates are unavailable'),
            'start_slots' => $startSlots,
            'end_slots' => $endSlots,
        ];
    }

    private function activeBookings(Vehicle $vehicle, CarbonImmutable $start, CarbonImmutable $end)
    {
        return Booking::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->whereDate('date_start', '<=', $end)
            ->whereDate('date_end', '>=', $start)
            ->get();
    }

    /**
     * Matrice occupazione [data][slot] => quantità, espandendo ogni booking:
     * giorno di inizio da time_start a chiusura, giorno di fine da apertura a
     * time_end, giorni intermedi pieni (ex getHoursCnt/getHoursCntByStock).
     */
    private function occupancy($bookings): array
    {
        $occupancy = [];

        foreach ($bookings as $booking) {
            $expanded = $this->expandBooking(
                CarbonImmutable::parse($booking->date_start),
                CarbonImmutable::parse($booking->date_end),
                $booking->time_start ? substr($booking->time_start, 0, 5) : null,
                $booking->time_end ? substr($booking->time_end, 0, 5) : null,
                max(1, (int) $booking->quantity),
            );
            foreach ($expanded as $day => $slots) {
                foreach ($slots as $slot => $qty) {
                    $occupancy[$day][$slot] = ($occupancy[$day][$slot] ?? 0) + $qty;
                }
            }
        }

        return $occupancy;
    }

    private function expandBooking(
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $timeStart,
        ?string $timeEnd,
        int $quantity,
    ): array {
        $result = [];
        $startKey = $start->toDateString();
        $endKey = $end->toDateString();

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $key = $day->toDateString();

            if ($startKey === $endKey) {
                $slots = $this->grid->slots($timeStart, $timeEnd);
            } elseif ($key === $startKey) {
                $slots = $this->grid->slots($timeStart, null);
            } elseif ($key === $endKey) {
                $slots = $this->grid->slots(null, $timeEnd);
            } else {
                $slots = $this->grid->slots();
            }

            foreach ($slots as $slot) {
                $result[$key][$slot] = ($result[$key][$slot] ?? 0) + $quantity;
            }
        }

        return $result;
    }

    /** Conteggio per giorno intero (per i giorni interni al range). */
    private function dailyCount($bookings, CarbonImmutable $day): int
    {
        $count = 0;
        foreach ($bookings as $booking) {
            if ($day->betweenIncluded(CarbonImmutable::parse($booking->date_start), CarbonImmutable::parse($booking->date_end))) {
                $count += max(1, (int) $booking->quantity);
            }
        }

        return $count;
    }

    private function freeSlots(array $occupancy, string $day, int $stock, int $quantity): array
    {
        $slots = [];
        foreach ($this->grid->slots() as $slot) {
            if (($occupancy[$day][$slot] ?? 0) + $quantity <= $stock) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * Slot già impegnati nello stesso hub in quella data: un ritiro/consegna
     * per slot per hub (lo staff non può essere in due posti).
     */
    private function hubBusySlots(?Location $location, CarbonImmutable $date, Vehicle $vehicle): array
    {
        if (! $location || ! $location->hub_id) {
            return [];
        }

        $hubLocationIds = Location::where('hub_id', $location->hub_id)->pluck('id');

        $events = Booking::query()
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->where(function ($query) use ($hubLocationIds, $date) {
                $query->where(function ($q) use ($hubLocationIds, $date) {
                    $q->whereIn('pickup_location_id', $hubLocationIds)
                        ->whereDate('date_start', $date);
                })->orWhere(function ($q) use ($hubLocationIds, $date) {
                    $q->whereIn('dropoff_location_id', $hubLocationIds)
                        ->whereDate('date_end', $date);
                });
            })
            ->get();

        $busy = [];
        foreach ($events as $booking) {
            if ($booking->time_start && $booking->date_start->isSameDay($date)) {
                $busy[] = substr($booking->time_start, 0, 5);
            }
            if ($booking->time_end && $booking->date_end->isSameDay($date)) {
                $busy[] = substr($booking->time_end, 0, 5);
            }
        }

        return array_values(array_unique($busy));
    }

    /**
     * Same-day: l'orario di fine deve essere almeno uno slot dopo l'inizio
     * (ex checkFasciaInizioIfFasciaFineNotEmpty / checkFasciaFineByStartTime).
     */
    private function sameDayConstrain(array $startSlots, array $endSlots, ?string $timeStart): array
    {
        $validStarts = [];
        foreach ($startSlots as $slot) {
            $minEnd = $this->grid->next($slot);
            if (array_filter($endSlots, fn ($e) => $this->grid->toMinutes($e) >= $this->grid->toMinutes($minEnd))) {
                $validStarts[] = $slot;
            }
        }

        $reference = ($timeStart && in_array($timeStart, $validStarts, true))
            ? $timeStart
            : ($validStarts[0] ?? null);

        $validEnds = [];
        if ($reference !== null) {
            $min = $this->grid->toMinutes($reference) + $this->grid->slotMinutes;
            $validEnds = array_values(array_filter(
                $endSlots,
                fn ($e) => $this->grid->toMinutes($e) >= $min,
            ));
        }

        return [$validStarts, $validEnds];
    }

    /**
     * Multi-day: le fasce di inizio valide sono la coda contigua fino alla
     * chiusura; i buchi dovuti alle esclusioni hub non spezzano la sequenza
     * (ex checkFasciaInizioByMoreDays).
     */
    private function contiguousTail(array $slots, array $tolerated = []): array
    {
        $result = [];

        foreach (array_reverse($this->grid->slots()) as $slot) {
            if (in_array($slot, $slots, true)) {
                $result[] = $slot;
            } elseif (! in_array($slot, $tolerated, true)) {
                break;
            }
        }

        return array_reverse($result);
    }

    /**
     * Multi-day: fasce di fine = testa contigua dall'apertura, tollerando i
     * buchi delle esclusioni hub (ex checkFasciaFineByMoreDays).
     */
    private function contiguousHead(array $slots, array $tolerated = []): array
    {
        $result = [];

        foreach ($this->grid->slots() as $slot) {
            if (in_array($slot, $slots, true)) {
                $result[] = $slot;
            } elseif (! in_array($slot, $tolerated, true)) {
                break;
            }
        }

        return $result;
    }

    /** Finestra oraria della località (ex fascia*PerLuoghiSpecifici). */
    private function intersectLocationWindow(array $slots, ?Location $location): array
    {
        if (! $location) {
            return $slots;
        }

        if ($location->endpoints_only) {
            $window = [$this->grid->dayStart, $this->grid->dayEnd];
        } elseif ($location->window_start || $location->window_end) {
            $window = $this->grid->slots(
                $location->window_start ? substr($location->window_start, 0, 5) : null,
                $location->window_end ? substr($location->window_end, 0, 5) : null,
            );
        } else {
            return $slots;
        }

        return array_values(array_intersect($slots, $window));
    }
}
