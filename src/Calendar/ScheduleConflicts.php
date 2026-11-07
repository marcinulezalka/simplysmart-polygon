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

use Illuminate\Database\Eloquent\Builder;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceSchedule;
use Simplysmart\Polygon\Queries\Leases\ResourceLeases;
use Simplysmart\Polygon\Queries\Organizations\OrganizationResources;

/**
 * Kolizje grafiku przed zapisem: przedział musi mieścić co najmniej jeden slot (albo minutę bez slotów), a okres
 * ważności nie może kończyć się przed początkiem; zasób musi być aktywnym zasobem jednostki albo jej przodka albo
 * zasobem wynajętym jej jako kooperacji; w tym dniu tygodnia, w pokrywającym się okresie ważności, przedział nie może
 * nachodzić na inny grafik tego zasobu (dowolny wykonawca i jednostka) ani tego wykonawcy (dowolny zasób i jednostka).
 * Najem (Models\ResourceLease): właściciel nie układa grafiku w oknach wynajętych w tym okresie (LeasedOut), a najemca
 * tylko w oknach i okresie swojej umowy (OutsideLease; umowa z końcem wymaga grafiku z końcem nie później). Cykl tygodni
 * (Calendar\WeekCycle): kolidują tylko grafiki i najmy o wspólnych tygodniach, a grafik najemcy mieści się w cyklu umowy.
 * Grafik wypełniający wolny czas (`fillsGaps`) nie zajmuje zasobu: nie koliduje z grafikiem zasobu ani oknami najmu,
 * a przy najmie na żądanie (Enums\LeaseMode::OnDemand) grafik najemcy musi być takim grafikiem.
 */
final readonly class ScheduleConflicts
{
    /** Najkrótszy slot w minutach. */
    public const int MIN_SLOT_MINUTES = 5;

    public function __construct(
        private OrganizationResources $resources,
        private ResourceLeases $leases,
    ) {}

    /**
     * @param int|null $ignoreId Edytowany grafik (nie koliduje sam ze sobą).
     *
     * @return list<ScheduleConflict>
     */
    public function of(ScheduleInput $schedule, ?int $ignoreId = null): array
    {
        $conflicts = [];
        $unit = Organization::query()->find($schedule->unitId);
        $length = $schedule->slotMinutes ?? 1;

        if ($length < ($schedule->slotMinutes === null ? 1 : self::MIN_SLOT_MINUTES) || $schedule->minutes() < $length || ($schedule->validTo !== null && $schedule->validTo->lessThan($schedule->validFrom)) || $schedule->weekInterval < 1 || $schedule->weekInterval > WeekCycle::MAX_INTERVAL) {
            $conflicts[] = ScheduleConflict::InvalidRange;
        }

        if (! $unit instanceof Organization || ! $this->resources->includes($unit, $schedule->resourceId)) {
            $conflicts[] = ScheduleConflict::ResourceUnavailable;
        } else {
            $lease = $this->leaseConflict($unit, $schedule);

            if ($lease !== null) {
                $conflicts[] = $lease;
            }
        }

        if (! $schedule->fillsGaps && $this->coinciding($schedule, $this->overlapping($schedule, $ignoreId)->where(OrganizationResource::FOREIGN_KEY, $schedule->resourceId)->where(ResourceSchedule::FILLS_GAPS, false))) {
            $conflicts[] = ScheduleConflict::ResourceTaken;
        }

        $performer = $schedule->performer;

        if ($performer !== null && $this->coinciding($schedule, $this->overlapping($schedule, $ignoreId)->where(ResourceSchedule::PERFORMER_TYPE, $performer->getMorphClass())->where(ResourceSchedule::PERFORMER_ID, $performer->id))) {
            $conflicts[] = ScheduleConflict::PerformerBusy;
        }

        return $conflicts;
    }

    /**
     * Najem zasobu: grafik na własnym zasobie nie wchodzi w okno aktywnego najmu w nakładającym się okresie (LeasedOut),
     * a grafik na zasobie wynajętym mieści się w oknach i okresie jednej umowy jednostki kooperacji (OutsideLease).
     */
    private function leaseConflict(Organization $unit, ScheduleInput $schedule): ?ScheduleConflict
    {
        $from = substr($schedule->startTime(), 0, 5);
        $to = substr($schedule->endTime(), 0, 5);

        if ($this->resources->owns($unit, $schedule->resourceId)) {
            foreach ($this->leases->overlapping($schedule->resourceId, $schedule->validFrom, $schedule->validTo) as $lease) {
                if ($lease->isExclusive() && ! $schedule->fillsGaps && $lease->leaseWindows()->overlaps($schedule->weekday, $from, $to) && $lease->cycle()->coincides($schedule->cycle())) {
                    return ScheduleConflict::LeasedOut;
                }
            }

            return null;
        }

        foreach ($this->leases->ofCooperation($unit, $schedule->resourceId) as $lease) {
            if ($lease->coversPeriod($schedule->validFrom, $schedule->validTo) && $lease->leaseWindows()->covers($schedule->weekday, $from, $to) && $schedule->cycle()->within($lease->cycle()) && ($lease->isExclusive() || $schedule->fillsGaps)) {
                return null;
            }
        }

        return ScheduleConflict::OutsideLease;
    }

    /**
     * Czy któryś grafik z zapytania ma tygodnie wspólne z cyklem nowego grafiku (np. dwa grafiki co 2 tygodnie
     * w tygodnie na zmianę nie kolidują).
     *
     * @param Builder<ResourceSchedule> $query
     */
    private function coinciding(ScheduleInput $schedule, Builder $query): bool
    {
        $cycle = $schedule->cycle();

        return $query->get()->contains(static fn(ResourceSchedule $other): bool => $other->cycle()->coincides($cycle));
    }

    /**
     * Aktywny grafik w tym dniu tygodnia, nachodzący na przedział i okres ważności.
     *
     * @return Builder<ResourceSchedule>
     */
    private function overlapping(ScheduleInput $schedule, ?int $ignoreId): Builder
    {
        $query = ResourceSchedule::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->where(ResourceSchedule::WEEKDAY, $schedule->weekday);
        $query->where(ResourceSchedule::STARTS_AT, '<', $schedule->endTime());
        $query->where(ResourceSchedule::ENDS_AT, '>', $schedule->startTime());
        $query->where(static function (Builder $period) use ($schedule): void {
            $period->whereNull(ResourceSchedule::VALID_TO)->orWhereDate(ResourceSchedule::VALID_TO, '>=', $schedule->validFrom);
        });

        if ($schedule->validTo !== null) {
            $query->whereDate(ResourceSchedule::VALID_FROM, '<=', $schedule->validTo);
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query;
    }
}
