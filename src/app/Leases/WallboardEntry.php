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

/**
 * Wiersz publicznej tablicy (Wallboard): przedział, wykonawca (nazwa konta, np. lekarz), zasób (etykieta) i liczba
 * zajętych terminów — bez danych osób zapisanych.
 */
final readonly class WallboardEntry
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public string $performer,
        public string $resource,
        public int $booked,
    ) {}
}
