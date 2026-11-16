<?php

declare(strict_types=1);

/**
 * This file is part of simplysmart/polygon.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

namespace Simplysmart\Polygon\Calendar;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\BookingStatus;
use Simplysmart\Polygon\Enums\RoomTakenBy;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceBooking;
use Simplysmart\Polygon\Models\ResourceLease;
use Simplysmart\Polygon\Models\ResourceSchedule;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Queries\Dictionaries\HolidaysIn;
use Simplysmart\Polygon\Queries\Leases\ResourceLeases;
use Simplysmart\Polygon\Queries\Organizations\OrganizationResources;

/**
 * Wolne terminy wykonawcy w jednostce: sloty po `slot_minutes` w jego grafiku obowiązującym w danym dniu (grafik bez
 * slotów nie daje terminów), bez terminów zajętych przez rezerwacje tego wykonawcy albo tego zasobu (w dowolnej
 * jednostce), bez dni minionych (dzisiejszy grafik cały — także sloty, których godzina już minęła, bo kolejka bloku
 * trwa i recepcja zapisuje na nie pacjentów) i bez dni wolnych kraju aplikacji (Queries\Dictionaries\HolidaysIn). Grafik na zasobie
 * wynajętym jednostce (kooperacja najemcy) daje terminy tylko w dniu, w którym umowa najmu obowiązuje i obejmuje jego
 * godziny — po końcu albo wypowiedzeniu umowy terminów nie ma, więc BookSlot ich też nie zapisze. Grafik wypełniający
 * wolny czas (`fills_gaps`, najem na żądanie) daje terminy tylko poza innym grafikiem zasobu i oknami najmów na
 * wyłączność innych jednostek. Rezerwacje nadliczbowe (ResourceBooking::isOverflow()) slotów nie zajmują.
 */
final readonly class FreeSlots
{
    public function __construct(
        private HolidaysIn $holidays,
        private OrganizationResources $resources,
        private ResourceLeases $leases,
    ) {}

    /**
     * Wolne terminy wykonawcy w jednostce w dniu (opcjonalnie tylko na zasobie $resourceId).
     *
     * @return list<Slot>
     */
    public function on(Organization $unit, ZoneAccount $performer, CarbonImmutable $day, ?int $resourceId = null): array
    {
        return $this->between($unit, $performer, $day, $day, $resourceId)[$day->toDateString()] ?? [];
    }

    /**
     * Najbliższe dni (od $from, przez $days dni) z wolnymi terminami wykonawcy w jednostce (opcjonalnie tylko na zasobie
     * $resourceId).
     *
     * @return list<CarbonImmutable>
     */
    public function days(Organization $unit, ZoneAccount $performer, CarbonImmutable $from, int $days, ?int $resourceId = null): array
    {
        if ($days < 1) {
            return [];
        }

        $start = $from->startOfDay();
        $free = $this->between($unit, $performer, $start, $start->addDays($days - 1), $resourceId);

        return array_map(static fn(string $date): CarbonImmutable => CarbonImmutable::parse($date), array_keys($free));
    }

    /**
     * Wolne terminy wykonawcy w jednostce w dniach od $from do $to (włącznie), opcjonalnie tylko na zasobie $resourceId:
     * grafiki, rezerwacje, dni wolne, inne grafiki zasobów i najmy wczytane raz na cały okres, siatka liczona w pamięci
     * dzień po dniu — reguły jak on(), bez zapytań na każdy dzień.
     *
     * @return array<string, list<Slot>> Data (`Y-m-d`) => wolne sloty od najwcześniejszego; dni bez slotów pominięte.
     */
    public function between(Organization $unit, ZoneAccount $performer, CarbonImmutable $from, CarbonImmutable $to, ?int $resourceId = null): array
    {
        $from = $from->startOfDay()->max(CarbonImmutable::today());
        $to = $to->startOfDay();

        if ($to->lessThan($from)) {
            return [];
        }

        $query = ResourceSchedule::query();
        $query->where(Organization::FOREIGN_KEY, $unit->id);
        $query->where(ResourceSchedule::PERFORMER_TYPE, $performer->getMorphClass());
        $query->where(ResourceSchedule::PERFORMER_ID, $performer->id);
        $query->where(ColumnNames::IS_ACTIVE, true);

        if ($resourceId !== null) {
            $query->where(OrganizationResource::FOREIGN_KEY, $resourceId);
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $resourceIds = array_values(array_unique(array_map(static fn(ResourceSchedule $schedule): int => $schedule->resource_id, $schedules->all())));
        $gapResourceIds = array_values(array_unique(array_map(static fn(ResourceSchedule $schedule): int => $schedule->resource_id, $schedules->filter(static fn(ResourceSchedule $schedule): bool => $schedule->fills_gaps)->all())));
        $daysOff = $this->holidays->applicationDaysOff($from, $to);
        $taken = $this->taken($performer, $resourceIds, $from, $to->addDay());
        $blockers = $this->blockingSchedules($gapResourceIds);
        $exclusive = [];
        $allows = [];
        $lineage = $gapResourceIds === [] ? [] : $this->resources->lineage($unit);
        $free = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            if (isset($daysOff[$day->toDateString()])) {
                continue;
            }

            $slots = [];

            foreach ($schedules as $schedule) {
                if ($schedule->weekday !== $day->dayOfWeekIso || ! $schedule->appliesOn($day) || ! $this->leaseAllows($unit, $schedule, $day, $allows)) {
                    continue;
                }

                $blocked = [];

                if ($schedule->fills_gaps) {
                    $exclusive[$schedule->resource_id] ??= $this->leases->overlapping($schedule->resource_id, $from, $to);
                    $blocked = $this->blocked($blockers[$schedule->resource_id] ?? [], $exclusive[$schedule->resource_id], $lineage, $day);
                }

                foreach ($this->grid($schedule, $day) as [$start, $end]) {
                    if ($this->overlaps($taken, $performer, $schedule->resource_id, $start, $end) || self::within($blocked, $start, $end)) {
                        continue;
                    }

                    $slots[] = new Slot($start, $end, $schedule->id, $schedule->resource_id);
                }
            }

            if ($slots !== []) {
                usort($slots, static fn(Slot $a, Slot $b): int => $a->startsAt <=> $b->startsAt);
                $free[$day->toDateString()] = $slots;
            }
        }

        return $free;
    }

    /**
     * Slot zaczynający się dokładnie o $startsAt (wolny w tej chwili), albo null.
     */
    public function at(Organization $unit, ZoneAccount $performer, CarbonImmutable $startsAt): ?Slot
    {
        foreach ($this->on($unit, $performer, $startsAt) as $slot) {
            if ($slot->startsAt->equalTo($startsAt)) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Grafik wykonawcy w jednostce obejmujący chwilę $at (do wyboru zasobu przed blokadą), albo null.
     */
    public function scheduleAt(Organization $unit, ZoneAccount $performer, CarbonImmutable $at): ?ResourceSchedule
    {
        $time = $at->format('H:i:s');

        return $this->schedules($unit, $performer, $at)->first(static fn(ResourceSchedule $schedule): bool => $schedule->starts_at <= $time && $schedule->ends_at > $time);
    }

    /**
     * Blok grafiku ze slotami wykonawcy w jednostce, który kończy się o $at (dzień tygodnia, okres ważności, cykl tygodni
     * i najem jak przy wolnych slotach) — po jego końcu stoi kolejka rezerwacji nadliczbowych (BookSlot::bookOverflow()).
     * Grafik na żądanie (`fills_gaps`) i bez slotów — pomijany.
     */
    public function scheduleEndingAt(Organization $unit, ZoneAccount $performer, CarbonImmutable $at): ?ResourceSchedule
    {
        if ($at->second !== 0) {
            return null;
        }

        $time = $at->format('H:i');

        return $this->schedules($unit, $performer, $at)->first(static fn(ResourceSchedule $schedule): bool => ! $schedule->fills_gaps && $schedule->slot_minutes !== null && $schedule->slot_minutes > 0 && $schedule->to() === $time);
    }

    /**
     * Godzina kolejnej rezerwacji nadliczbowej bloku grafiku w dniu $day (ResourceSchedule::nextOverflowAt() z rezerwacji
     * jego jednostki, zasobu i wykonawcy w tym dniu).
     */
    public function nextOverflow(ResourceSchedule $schedule, CarbonImmutable $day): CarbonImmutable
    {
        $day = $day->startOfDay();
        $query = ResourceBooking::query();
        $query->where(Organization::FOREIGN_KEY, $schedule->organization_id);
        $query->where(OrganizationResource::FOREIGN_KEY, $schedule->resource_id);
        $query->where(ResourceSchedule::PERFORMER_TYPE, $schedule->performer_type);
        $query->where(ResourceSchedule::PERFORMER_ID, $schedule->performer_id);
        $query->whereIn(ColumnNames::STATUS, BookingStatus::occupyingValues());
        $query->where(ResourceBooking::STARTS_AT, '>=', $day);
        $query->where(ResourceBooking::STARTS_AT, '<', $day->addDay());

        return $schedule->nextOverflowAt($day, $query->get());
    }

    /**
     * Czy zasób bloku grafiku jest dostępny przez slot kolejnej rezerwacji nadliczbowej (nextOverflow() — co
     * `slot_minutes` po końcu bloku, BookSlot::bookOverflow()). Null — dostępny; RoomTaken — pierwsza kolizja w oknie
     * [kolejna nadliczbowa, + slot): inny grafik zasobu (bez wypełniających), okno najmu na wyłączność innej jednostki
     * albo rezerwacja zajmująca termin innego wykonawcy na zasobie. Koniec własnego najmu jednostki (kooperacji) po bloku
     * kolizją nie jest.
     */
    public function roomTakenAfter(Organization $unit, ResourceSchedule $schedule, CarbonImmutable $day): ?RoomTaken
    {
        $day = $day->startOfDay();
        $start = $this->nextOverflow($schedule, $day);
        $end = $start->addMinutes(max(1, (int) $schedule->slot_minutes));
        $taken = [];
        $query = ResourceSchedule::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->where(OrganizationResource::FOREIGN_KEY, $schedule->resource_id);
        $query->where(ResourceSchedule::WEEKDAY, $day->dayOfWeekIso);
        $query->where(ResourceSchedule::FILLS_GAPS, false);
        $query->whereKeyNot($schedule->id);

        foreach ($query->get() as $other) {
            $from = $day->setTimeFromTimeString($other->starts_at);

            if ($other->appliesOn($day) && self::within([[$from, $day->setTimeFromTimeString($other->ends_at)]], $start, $end)) {
                $taken[] = new RoomTaken($from->max($start), RoomTakenBy::Schedule, $other->performerAccount(), $other->organization);
            }
        }

        foreach ($this->exclusiveLeaseIntervals($unit, $schedule, $day) as [$lease, $from, $to]) {
            if (self::within([[$from, $to]], $start, $end)) {
                $taken[] = new RoomTaken($from->max($start), RoomTakenBy::Lease, null, $lease->cooperation);
            }
        }

        $bookings = ResourceBooking::query();
        $bookings->whereIn(ColumnNames::STATUS, BookingStatus::occupyingValues());
        $bookings->where(OrganizationResource::FOREIGN_KEY, $schedule->resource_id);
        $bookings->where(ResourceBooking::STARTS_AT, '<', $end);
        $bookings->where(ResourceBooking::ENDS_AT, '>', $start);

        foreach ($bookings->get() as $booking) {
            if ($booking->performer_type !== $schedule->performer_type || $booking->performer_id !== $schedule->performer_id) {
                $taken[] = new RoomTaken($booking->starts_at->max($start), RoomTakenBy::Booking, $booking->performerAccount(), $booking->organization);
            }
        }

        usort($taken, static fn(RoomTaken $a, RoomTaken $b): int => $a->from <=> $b->from);

        return $taken[0] ?? null;
    }

    /**
     * Początki i końce slotów grafiku w dniu (grafik bez slotów — brak).
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function grid(ResourceSchedule $schedule, CarbonImmutable $day): array
    {
        $minutes = $schedule->slot_minutes;

        if ($minutes === null || $minutes < 1) {
            return [];
        }

        $end = $day->setTimeFromTimeString($schedule->ends_at);
        $grid = [];

        for ($slot = $day->setTimeFromTimeString($schedule->starts_at); $slot->addMinutes($minutes)->lessThanOrEqualTo($end); $slot = $slot->addMinutes($minutes)) {
            $grid[] = [$slot, $slot->addMinutes($minutes)];
        }

        return $grid;
    }

    /**
     * Aktywny grafik wykonawcy w jednostce obowiązujący w dniu.
     *
     * @return Collection<int, ResourceSchedule>
     */
    private function schedules(Organization $unit, ZoneAccount $performer, CarbonImmutable $day): Collection
    {
        $query = ResourceSchedule::query();
        $query->where(Organization::FOREIGN_KEY, $unit->id);
        $query->where(ResourceSchedule::PERFORMER_TYPE, $performer->getMorphClass());
        $query->where(ResourceSchedule::PERFORMER_ID, $performer->id);
        $query->where(ResourceSchedule::WEEKDAY, $day->dayOfWeekIso);
        $query->where(ColumnNames::IS_ACTIVE, true);

        $allows = [];

        return $query->get()->filter(fn(ResourceSchedule $schedule): bool => $schedule->appliesOn($day) && $this->leaseAllows($unit, $schedule, $day, $allows))->values();
    }

    /**
     * Czas zasobu zajęty w dniu dla grafiku wypełniającego wolny czas: inny grafik zasobu (bez wypełniających,
     * $blockers z blockingSchedules()) i okna najmów na wyłączność innych jednostek ($leases zasobu w okresie, jednostki
     * spoza $lineage) obowiązujące w tym dniu.
     *
     * @param list<ResourceSchedule> $blockers
     * @param Collection<int, ResourceLease> $leases
     * @param list<int> $lineage
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function blocked(array $blockers, Collection $leases, array $lineage, CarbonImmutable $day): array
    {
        $blocked = [];

        foreach ($blockers as $other) {
            if ($other->weekday === $day->dayOfWeekIso && $other->appliesOn($day)) {
                $blocked[] = [$day->setTimeFromTimeString($other->starts_at), $day->setTimeFromTimeString($other->ends_at)];
            }
        }

        foreach (self::exclusiveIntervals($leases, $lineage, $day) as [, $from, $to]) {
            $blocked[] = [$from, $to];
        }

        return $blocked;
    }

    /**
     * Grafiki zasobów $resourceIds, które blokują grafik wypełniający wolny czas (aktywne, bez wypełniających), po zasobie.
     *
     * @param list<int> $resourceIds
     *
     * @return array<int, list<ResourceSchedule>>
     */
    private function blockingSchedules(array $resourceIds): array
    {
        if ($resourceIds === []) {
            return [];
        }

        $query = ResourceSchedule::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->whereIn(OrganizationResource::FOREIGN_KEY, $resourceIds);
        $query->where(ResourceSchedule::FILLS_GAPS, false);
        $byResource = [];

        foreach ($query->get() as $schedule) {
            $byResource[$schedule->resource_id][] = $schedule;
        }

        return $byResource;
    }

    /**
     * Okna najmów na wyłączność zasobu grafiku obowiązujące w dniu, z najmem: najmy innych jednostek niż $unit (i jej
     * przodkowie); najem bez okien zajmuje cały dzień.
     *
     * @return list<array{0: ResourceLease, 1: CarbonImmutable, 2: CarbonImmutable}>
     */
    private function exclusiveLeaseIntervals(Organization $unit, ResourceSchedule $schedule, CarbonImmutable $day): array
    {
        return self::exclusiveIntervals($this->leases->overlapping($schedule->resource_id, $day, $day), $this->resources->lineage($unit), $day);
    }

    /**
     * Okna najmów $leases na wyłączność obowiązujące w dniu — najmy jednostek spoza $lineage; najem bez okien zajmuje
     * cały dzień.
     *
     * @param Collection<int, ResourceLease> $leases
     * @param list<int> $lineage
     *
     * @return list<array{0: ResourceLease, 1: CarbonImmutable, 2: CarbonImmutable}>
     */
    private static function exclusiveIntervals(Collection $leases, array $lineage, CarbonImmutable $day): array
    {
        $intervals = [];

        foreach ($leases as $lease) {
            if (! $lease->isExclusive() || in_array($lease->cooperation_organization_id, $lineage, true) || ! $lease->occursOn($day)) {
                continue;
            }

            $windows = $lease->leaseWindows();

            if ($windows->isWhole()) {
                $intervals[] = [$lease, $day, $day->addDay()];
            }

            foreach ($windows->windows as [$weekday, $from, $to]) {
                if ($weekday === $day->dayOfWeekIso) {
                    $intervals[] = [$lease, $day->setTimeFromTimeString($from), $day->setTimeFromTimeString($to)];
                }
            }
        }

        return $intervals;
    }

    /**
     * @param list<array{0: CarbonImmutable, 1: CarbonImmutable}> $intervals
     */
    private static function within(array $intervals, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        foreach ($intervals as [$from, $to]) {
            if ($from->lessThan($end) && $to->greaterThan($start)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Grafik na własnym zasobie zawsze; na wynajętym — gdy w dniu obowiązuje umowa jednostki obejmująca jego godziny.
     * Przynależność zasobu i najmy kooperacji są liczone raz na zasób i pamiętane w $known między dniami okresu.
     *
     * @param array<int, Collection<int, ResourceLease>|true> $known Id zasobu => true (własny) albo najmy kooperacji.
     */
    private function leaseAllows(Organization $unit, ResourceSchedule $schedule, CarbonImmutable $day, array &$known): bool
    {
        $known[$schedule->resource_id] ??= $this->resources->owns($unit, $schedule->resource_id) ? true : $this->leases->ofCooperation($unit, $schedule->resource_id);
        $leases = $known[$schedule->resource_id];

        if ($leases === true) {
            return true;
        }

        foreach ($leases as $lease) {
            if ($lease->occursOn($day) && $lease->leaseWindows()->covers($schedule->weekday, $schedule->from(), $schedule->to())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rezerwacje zajmujące termin w okresie [$from, $until) u wykonawcy albo na jego zasobach.
     *
     * @param list<int> $resourceIds
     *
     * @return Collection<int, ResourceBooking>
     */
    private function taken(ZoneAccount $performer, array $resourceIds, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        $query = ResourceBooking::query();
        $query->whereIn(ColumnNames::STATUS, BookingStatus::occupyingValues());
        $query->where(ResourceBooking::STARTS_AT, '<', $until);
        $query->where(ResourceBooking::ENDS_AT, '>', $from);
        $query->where(static function (Builder $owner) use ($performer, $resourceIds): void {
            $owner->where(static function (Builder $own) use ($performer): void {
                $own->where(ResourceSchedule::PERFORMER_TYPE, $performer->getMorphClass())->where(ResourceSchedule::PERFORMER_ID, $performer->id);
            })->orWhereIn(OrganizationResource::FOREIGN_KEY, $resourceIds);
        });

        return $query->get();
    }

    /**
     * Czy slot [$start, $end) zajmuje rezerwacja wykonawcy albo zasobu — poza nadliczbowymi, które slotów nie zajmują.
     *
     * @param Collection<int, ResourceBooking> $taken
     */
    private function overlaps(Collection $taken, ZoneAccount $performer, int $resourceId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $taken->contains(static fn(ResourceBooking $booking): bool => ! $booking->isOverflow()
            && (($booking->performer_type === $performer->getMorphClass() && $booking->performer_id === $performer->id) || $booking->resource_id === $resourceId)
            && $booking->starts_at->lessThan($end)
            && $booking->ends_at->greaterThan($start));
    }
}
