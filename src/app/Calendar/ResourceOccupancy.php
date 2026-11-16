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
use Illuminate\Database\Eloquent\Collection;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceLease;
use Simplysmart\Polygon\Models\ResourceSchedule;
use Simplysmart\Polygon\Queries\Leases\ResourceLeases;
use Simplysmart\Polygon\Queries\Organizations\OrganizationResources;

/**
 * Zajętość zasobu poza rezerwacjami: inne grafiki zasobu, które blokują grafik wypełniający wolny czas, i okna najmów
 * na wyłączność innych jednostek — dla FreeSlots (wolne terminy, kolizje po bloku grafiku).
 */
final readonly class ResourceOccupancy
{
    public function __construct(
        private OrganizationResources $resources,
        private ResourceLeases $leases,
    ) {}

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
    public function blocked(array $blockers, Collection $leases, array $lineage, CarbonImmutable $day): array
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
    public function blockingSchedules(array $resourceIds): array
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
    public function exclusiveLeaseIntervals(Organization $unit, ResourceSchedule $schedule, CarbonImmutable $day): array
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
    public static function exclusiveIntervals(Collection $leases, array $lineage, CarbonImmutable $day): array
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
    public static function within(array $intervals, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        foreach ($intervals as [$from, $to]) {
            if ($from->lessThan($end) && $to->greaterThan($start)) {
                return true;
            }
        }

        return false;
    }
}
