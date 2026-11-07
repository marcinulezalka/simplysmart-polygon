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
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceBooking;
use Simplysmart\Polygon\Models\ResourceSchedule;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Queries\Dictionaries\HolidaysIn;

/**
 * Wolne terminy wykonawcy w jednostce: sloty po `slot_minutes` w jego grafiku obowiązującym w danym dniu (grafik bez
 * slotów nie daje terminów), bez terminów zajętych przez rezerwacje tego wykonawcy albo tego zasobu (w dowolnej
 * jednostce), bez minionych i bez dni wolnych kraju aplikacji (Queries\Dictionaries\HolidaysIn).
 */
final readonly class FreeSlots
{
    public function __construct(
        private HolidaysIn $holidays,
    ) {}

    /**
     * @return list<Slot>
     */
    public function on(Organization $unit, ZoneAccount $performer, CarbonImmutable $day): array
    {
        $day = $day->startOfDay();

        if ($this->holidays->applicationDaysOff($day, $day) !== []) {
            return [];
        }

        $schedules = $this->schedules($unit, $performer, $day);

        if ($schedules->isEmpty()) {
            return [];
        }

        $taken = $this->taken($performer, array_values(array_map(static fn(ResourceSchedule $schedule): int => $schedule->resource_id, $schedules->all())), $day);
        $now = CarbonImmutable::now();
        $slots = [];

        foreach ($schedules as $schedule) {
            foreach ($this->grid($schedule, $day) as [$start, $end]) {
                if ($start->lessThanOrEqualTo($now) || $this->overlaps($taken, $performer, $schedule->resource_id, $start, $end)) {
                    continue;
                }

                $slots[] = new Slot($start, $end, $schedule->id, $schedule->resource_id);
            }
        }

        usort($slots, static fn(Slot $a, Slot $b): int => $a->startsAt <=> $b->startsAt);

        return $slots;
    }

    /**
     * Najbliższe dni (od $from, przez $days dni) z wolnymi terminami wykonawcy w jednostce.
     *
     * @return list<CarbonImmutable>
     */
    public function days(Organization $unit, ZoneAccount $performer, CarbonImmutable $from, int $days): array
    {
        $result = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $day = $from->startOfDay()->addDays($offset);

            if ($this->on($unit, $performer, $day) !== []) {
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

        return $query->get()->filter(static fn(ResourceSchedule $schedule): bool => $schedule->appliesOn($day))->values();
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
