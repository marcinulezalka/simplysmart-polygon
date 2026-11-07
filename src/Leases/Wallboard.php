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

namespace Simplysmart\Polygon\Leases;

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Contracts\SignInAccount;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\BookingStatus;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceBooking;
use Simplysmart\Polygon\Models\ResourceLease;
use Simplysmart\Polygon\Models\ResourceSchedule;

/**
 * Publiczna tablica najmu (poziom Wallboard): godziny przyjęć kooperacji na wynajętym zasobie w dniu — bloki grafiku
 * obowiązujące w tym dniu, w oknach umowy, z wykonawcą i liczbą zajętych terminów. Tablica nie zawiera danych osób
 * zapisanych ani rodzaju rezerwacji; grafik wypełniający wolny czas (najem na żądanie) pokazuje tylko zapisane wizyty; dostęp po tokenie umowy (Queries\Leases\ResourceLeases::byWallboardToken()).
 */
final readonly class Wallboard
{
    /**
     * @return list<WallboardEntry>
     */
    public function of(ResourceLease $lease, CarbonImmutable $day): array
    {
        $day = $day->startOfDay();

        if (! $lease->occursOn($day)) {
            return [];
        }

        $query = ResourceSchedule::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->where(Organization::FOREIGN_KEY, $lease->cooperation_organization_id);
        $query->where(OrganizationResource::FOREIGN_KEY, $lease->resource_id);
        $query->where(ResourceSchedule::WEEKDAY, $day->dayOfWeekIso);
        $query->with(['performer', 'resource']);
        $query->orderBy(ResourceSchedule::STARTS_AT);
        $windows = $lease->leaseWindows();
        $bookings = $this->bookings($lease, $day);
        $entries = [];

        foreach ($query->get() as $schedule) {
            if (! $schedule->appliesOn($day) || ! $windows->covers($schedule->weekday, $schedule->from(), $schedule->to())) {
                continue;
            }

            $start = $day->setTimeFromTimeString($schedule->starts_at);
            $end = $day->setTimeFromTimeString($schedule->ends_at);
            $performer = $schedule->performer;

            if ($schedule->fills_gaps) {
                foreach ($bookings as $booking) {
                    if ($booking->performer_type === $schedule->performer_type && $booking->performer_id === $schedule->performer_id && $booking->starts_at->greaterThanOrEqualTo($start) && $booking->starts_at->lessThan($end)) {
                        $entries[] = new WallboardEntry($booking->starts_at, $booking->ends_at, $performer instanceof SignInAccount ? $performer->displayName() : '', $schedule->resource?->label() ?? '', 1);
                    }
                }

                continue;
            }

            $entries[] = new WallboardEntry(
                $start,
                $end,
                $performer instanceof SignInAccount ? $performer->displayName() : '',
                $schedule->resource?->label() ?? '',
                count(array_filter($bookings, static fn(ResourceBooking $booking): bool => $booking->performer_type === $schedule->performer_type
                    && $booking->performer_id === $schedule->performer_id
                    && $booking->starts_at->greaterThanOrEqualTo($start)
                    && $booking->starts_at->lessThan($end))),
            );
        }

        return $entries;
    }

    /**
     * @return list<ResourceBooking>
     */
    private function bookings(ResourceLease $lease, CarbonImmutable $day): array
    {
        $query = ResourceBooking::query();
        $query->where(Organization::FOREIGN_KEY, $lease->cooperation_organization_id);
        $query->where(OrganizationResource::FOREIGN_KEY, $lease->resource_id);
        $query->where(ResourceBooking::STARTS_AT, '>=', $day);
        $query->where(ResourceBooking::STARTS_AT, '<', $day->addDay());
        $query->whereIn(ColumnNames::STATUS, BookingStatus::occupyingValues());

        return array_values($query->get()->all());
    }
}
