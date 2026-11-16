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

namespace Simplysmart\Polygon\Tests\Fixtures\Zones;

/**
 * Strefa testowa open: tylko trasy publiczne, bez kont i logowania.
 */
class OpenZoneServiceProvider extends FixtureZoneServiceProvider
{
    protected function zoneName(): string
    {
        return 'open';
    }

    protected function zoneConfig(): array
    {
        return ['name' => 'open'];
    }
}
