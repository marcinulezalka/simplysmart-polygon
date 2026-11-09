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
use Simplysmart\Polygon\Models\ResourceSchedule;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Queries\Dictionaries\HolidaysIn;
use Simplysmart\Polygon\Queries\Leases\ResourceLeases;
use Simplysmart\Polygon\Queries\Organizations\OrganizationResources;

/**
 * Wolne terminy wykonawcy w jednostce: sloty po `slot_minutes` w jego grafiku obowiązującym w danym dniu (grafik bez
 * slotów nie daje terminów), bez terminów zajętych przez rezerwacje tego wykonawcy albo tego zasobu (w dowolnej
 * jednostce), bez minionych i bez dni wolnych kraju aplikacji (Queries\Dictionaries\HolidaysIn). Grafik na zasobie
 * wynajętym jednostce (kooperacja najemcy) daje terminy tylko w dniu, w którym umowa najmu obowiązuje i obejmuje jego
 * godziny — po końcu albo wypowiedzeniu umowy terminów nie ma, więc BookSlot ich też nie zapisze. Grafik wypełniający
 * wolny czas (`fills_gaps`, najem na żądanie) daje terminy tylko poza innym grafikiem zasobu i oknami najmów na
 * wyłączność innych jednostek.
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
        $day = $day->startOfDay();

        if ($this->holidays->applicationDaysOff($day, $day) !== []) {
            return [];
        }

        $schedules = $this->schedules($unit, $performer, $day);

        if ($resourceId !== null) {
            $schedules = $schedules->filter(static fn(ResourceSchedule $schedule): bool => $schedule->resource_id === $resourceId)->values();
        }

        if ($schedules->isEmpty()) {
            return [];
        }

        $taken = $this->taken($performer, array_values(array_map(static fn(ResourceSchedule $schedule): int => $schedule->resource_id, $schedules->all())), $day);
        $now = CarbonImmutable::now();
        $slots = [];

        foreach ($schedules as $schedule) {
            $blocked = $schedule->fills_gaps ? $this->blocked($unit, $schedule, $day) : [];

            foreach ($this->grid($schedule, $day) as [$start, $end]) {
                if ($start->lessThanOrEqualTo($now) || $this->overlaps($taken, $performer, $schedule->resource_id, $start, $end) || self::within($blocked, $start, $end)) {
                    continue;
                }

                $slots[] = new Slot($start, $end, $schedule->id, $schedule->resource_id);
            }
        }

        usort($slots, static fn(Slot $a, Slot $b): int => $a->startsAt <=> $b->startsAt);

        return $slots;
    }

    /**
     * Najbliższe dni (od $from, przez $days dni) z wolnymi terminami wykonawcy w jednostce (opcjonalnie tylko na zasobie
     * $resourceId).
     *
     * @return list<CarbonImmutable>
     */
    public function days(Organization $unit, ZoneAccount $performer, CarbonImmutable $from, int $days, ?int $resourceId = null): array
    {
        $result = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $day = $from->startOfDay()->addDays($offset);

            if ($this->on($unit, $performer, $day, $resourceId) !== []) {
                $result[] = $day;
            }
        }

        return $result;
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
     * i najem jak przy wolnych slotach) — koniec bloku to miejsce rezerwacji nadliczbowej (BookSlot::bookOverflow()).
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
     * Czy zasób bloku grafiku jest dostępny przez jeden slot po jego końcu — tam trafia rezerwacja nadliczbowa
     * (BookSlot::bookOverflow()). Null — dostępny; RoomTaken — pierwsza kolizja w oknie [koniec, koniec + slot): inny
     * grafik zasobu (bez wypełniających), okno najmu na wyłączność innej jednostki albo rezerwacja zajmująca termin
     * innego wykonawcy na zasobie. Koniec własnego najmu jednostki (kooperacji) po bloku kolizją nie jest.
     */
    public function roomTakenAfter(Organization $unit, ResourceSchedule $schedule, CarbonImmutable $day): ?RoomTaken
    {
        $day = $day->startOfDay();
        $start = $day->setTimeFromTimeString($schedule->ends_at);
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
                $performer = $other->performer;
                $taken[] = new RoomTaken($from->max($start), RoomTakenBy::Schedule, $performer instanceof ZoneAccount ? $performer : null, $other->organization);
            }
        }

        $lineage = $this->resources->lineage($unit);

        foreach ($this->leases->overlapping($schedule->resource_id, $day, $day) as $lease) {
            if (! $lease->isExclusive() || in_array($lease->cooperation_organization_id, $lineage, true) || ! $lease->occursOn($day)) {
                continue;
            }

            $windows = $lease->leaseWindows();
            $intervals = $windows->isWhole() ? [[$day, $day->addDay()]] : [];

            foreach ($windows->windows as [$weekday, $from, $to]) {
                if ($weekday === $day->dayOfWeekIso) {
                    $intervals[] = [$day->setTimeFromTimeString($from), $day->setTimeFromTimeString($to)];
                }
            }

            foreach ($intervals as [$from, $to]) {
                if (self::within([[$from, $to]], $start, $end)) {
                    $taken[] = new RoomTaken($from->max($start), RoomTakenBy::Lease, null, $lease->cooperation);
                }
            }
        }

        $bookings = ResourceBooking::query();
        $bookings->whereIn(ColumnNames::STATUS, BookingStatus::occupyingValues());
        $bookings->where(OrganizationResource::FOREIGN_KEY, $schedule->resource_id);
        $bookings->where(ResourceBooking::STARTS_AT, '<', $end);
        $bookings->where(ResourceBooking::ENDS_AT, '>', $start);

        foreach ($bookings->get() as $booking) {
            if ($booking->performer_type !== $schedule->performer_type || $booking->performer_id !== $schedule->performer_id) {
                $performer = $booking->performer;
                $taken[] = new RoomTaken($booking->starts_at->max($start), RoomTakenBy::Booking, $performer instanceof ZoneAccount ? $performer : null, $booking->organization);
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

        return $query->get()->filter(fn(ResourceSchedule $schedule): bool => $schedule->appliesOn($day) && $this->leaseAllows($unit, $schedule, $day))->values();
    }

    /**
     * Czas zasobu zajęty w dniu dla grafiku wypełniającego wolny czas: inny grafik zasobu (bez wypełniających) i okna
     * najmów na wyłączność innych jednostek obowiązujące w tym dniu.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function blocked(Organization $unit, ResourceSchedule $schedule, CarbonImmutable $day): array
    {
        $blocked = [];
        $query = ResourceSchedule::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->where(OrganizationResource::FOREIGN_KEY, $schedule->resource_id);
        $query->where(ResourceSchedule::WEEKDAY, $day->dayOfWeekIso);
        $query->where(ResourceSchedule::FILLS_GAPS, false);

        foreach ($query->get() as $other) {
            if ($other->appliesOn($day)) {
                $blocked[] = [$day->setTimeFromTimeString($other->starts_at), $day->setTimeFromTimeString($other->ends_at)];
            }
        }

        $lineage = $this->resources->lineage($unit);

        foreach ($this->leases->overlapping($schedule->resource_id, $day, $day) as $lease) {
            if (! $lease->isExclusive() || in_array($lease->cooperation_organization_id, $lineage, true) || ! $lease->occursOn($day)) {
                continue;
            }

            $windows = $lease->leaseWindows();

            if ($windows->isWhole()) {
                $blocked[] = [$day, $day->addDay()];
            }

            foreach ($windows->windows as [$weekday, $from, $to]) {
                if ($weekday === $day->dayOfWeekIso) {
                    $blocked[] = [$day->setTimeFromTimeString($from), $day->setTimeFromTimeString($to)];
                }
            }
        }

        return $blocked;
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
     */
    private function leaseAllows(Organization $unit, ResourceSchedule $schedule, CarbonImmutable $day): bool
    {
        if ($this->resources->owns($unit, $schedule->resource_id)) {
            return true;
        }

        foreach ($this->leases->ofCooperation($unit, $schedule->resource_id) as $lease) {
            if ($lease->occursOn($day) && $lease->leaseWindows()->covers($schedule->weekday, $schedule->from(), $schedule->to())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rezerwacje zajmujące termin w dniu u wykonawcy albo na jego zasobach.
     *
     * @param list<int> $resourceIds
     *
     * @return Collection<int, ResourceBooking>
     */
    private function taken(ZoneAccount $performer, array $resourceIds, CarbonImmutable $day): Collection
    {
        $query = ResourceBooking::query();
        $query->whereIn(ColumnNames::STATUS, BookingStatus::occupyingValues());
        $query->where(ResourceBooking::STARTS_AT, '<', $day->addDay());
        $query->where(ResourceBooking::ENDS_AT, '>', $day);
        $query->where(static function (Builder $owner) use ($performer, $resourceIds): void {
            $owner->where(static function (Builder $own) use ($performer): void {
                $own->where(ResourceSchedule::PERFORMER_TYPE, $performer->getMorphClass())->where(ResourceSchedule::PERFORMER_ID, $performer->id);
            })->orWhereIn(OrganizationResource::FOREIGN_KEY, $resourceIds);
        });

        return $query->get();
    }

    /**
     * @param Collection<int, ResourceBooking> $taken
     */
    private function overlaps(Collection $taken, ZoneAccount $performer, int $resourceId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $taken->contains(static fn(ResourceBooking $booking): bool => (($booking->performer_type === $performer->getMorphClass() && $booking->performer_id === $performer->id) || $booking->resource_id === $resourceId)
            && $booking->starts_at->lessThan($end)
            && $booking->ends_at->greaterThan($start));
    }
}
