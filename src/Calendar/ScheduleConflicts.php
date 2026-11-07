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
use Simplysmart\Polygon\Queries\Organizations\OrganizationResources;

/**
 * Kolizje grafiku przed zapisem: przedział musi mieścić co najmniej jeden slot (albo minutę bez slotów), a okres
 * ważności nie może kończyć się przed początkiem; zasób musi być aktywnym zasobem jednostki albo jej przodka; w tym dniu
 * tygodnia, w pokrywającym się okresie ważności, przedział nie może nachodzić na inny grafik tego zasobu (dowolny
 * wykonawca i jednostka) ani tego wykonawcy (dowolny zasób i jednostka).
 */
final readonly class ScheduleConflicts
{
    /** Najkrótszy slot w minutach. */
    public const int MIN_SLOT_MINUTES = 5;

    public function __construct(
        private OrganizationResources $resources,
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

        if ($length < ($schedule->slotMinutes === null ? 1 : self::MIN_SLOT_MINUTES) || $schedule->minutes() < $length || ($schedule->validTo !== null && $schedule->validTo->lessThan($schedule->validFrom))) {
            $conflicts[] = ScheduleConflict::InvalidRange;
        }

        if (! $unit instanceof Organization || ! $this->resources->includes($unit, $schedule->resourceId)) {
            $conflicts[] = ScheduleConflict::ResourceUnavailable;
        }

        if ($this->overlapping($schedule, $ignoreId)->where(OrganizationResource::FOREIGN_KEY, $schedule->resourceId)->exists()) {
            $conflicts[] = ScheduleConflict::ResourceTaken;
        }

        $performer = $schedule->performer;

        if ($performer !== null && $this->overlapping($schedule, $ignoreId)->where(ResourceSchedule::PERFORMER_TYPE, $performer->getMorphClass())->where(ResourceSchedule::PERFORMER_ID, $performer->id)->exists()) {
            $conflicts[] = ScheduleConflict::PerformerBusy;
        }

        return $conflicts;
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
