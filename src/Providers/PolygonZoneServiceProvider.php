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

namespace Simplysmart\Polygon\Providers;

namespace Simplysmart\Polygon\Providers;

use Illuminate\Config\Repository;
use Simplysmart\Polygon\PolygonConfig;
use Simplysmart\Polygon\PolygonServiceProvider;
use Simplysmart\Polygon\Zones\PolygonZone;
use Simplysmart\Polygon\Zones\ZoneServiceProvider;

/**
 * Strefa `polygon` (config `polygon.zone`): guard i provider kont `polygon_accounts` dopisane do config/auth przez
 * mechanizm stref, jak każda strefa paczki. Bez widoków i plików tras — tylko konta, role i guard.
 */
final class PolygonZoneServiceProvider extends ZoneServiceProvider
{
    protected function routeNamePrefix(): string
    {
        return PolygonZone::NAME;
    }

    protected function zoneConfig(): array
    {
        return $this->app->make(Repository::class)->array(PolygonConfig::ZONE, []);
    }

    protected function routesPath(): string
    {
        return PolygonServiceProvider::ROOT . '/routes';
    }
}
