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

namespace Simplysmart\Polygon\Console;

use Simplysmart\Polygon\Zones\PolygonZone;

/**
 * Zakłada konto strefy `polygon`: `polygon:account:create {email} {name} [--role=*] [--skip-existing]`
 * (odpowiednik `admiro:account:create`, logika w CreateZoneAccountCommand).
 */
final class CreateAccountCommand extends CreateZoneAccountCommand
{
    protected $name = 'polygon:account:create';

    protected $description = 'Zakłada konto strefy polygon';

    protected function zoneName(): string
    {
        return PolygonZone::NAME;
    }
}
