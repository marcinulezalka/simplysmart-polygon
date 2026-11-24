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
 * Strefa testowa beta z logowaniem, bez zaufanych urządzeń, z kontekstem organizacja + rola (`role_contexts`)
 * i własną stroną sekcji `recovery` (`auth.security_pages`).
 */
class BetaZoneServiceProvider extends FixtureZoneServiceProvider
{
    protected function zoneName(): string
    {
        return 'beta';
    }

    protected function zoneConfig(): array
    {
        return $this->signInZone(BetaAccount::class, ['auth' => ['trusted_devices' => ['enabled' => false], 'security_pages' => ['recovery' => 'dashboard']], 'role_contexts' => true]);
    }
}
