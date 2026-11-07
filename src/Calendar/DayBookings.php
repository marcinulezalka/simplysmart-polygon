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
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\ResourceBooking;

/**
 * Lista dnia: rezerwacje jednostek w dniu (wszystkie stany), opcjonalnie jednego rodzaju, od najwcześniejszej,
 * z wykonawcą, zasobem i jednostką.
 */
final readonly class DayBookings
{
    /**
     * @param list<int> $unitIds
     *
     * @return Collection<int, ResourceBooking>
     */
    public function of(array $unitIds, CarbonImmutable $day, ?string $kind = null): Collection
    {
        $day = $day->startOfDay();
        $query = ResourceBooking::query();
        $query->whereIn(Organization::FOREIGN_KEY, $unitIds);
        $query->where(ResourceBooking::STARTS_AT, '>=', $day);
        $query->where(ResourceBooking::STARTS_AT, '<', $day->addDay());

        if ($kind !== null) {
            $query->where(ResourceBooking::KIND, $kind);
        }

        $query->with(['performer', 'resource', 'organization']);
        $query->orderBy(ResourceBooking::STARTS_AT);

        return $query->get();
    }
}
