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

namespace Simplysmart\Polygon\Zones;

use BackedEnum;
use Simplysmart\Polygon\Http\Middleware\RequireZonePermission;

/**
 * Middleware trasy wymagającej uprawnienia w strefie, np. w `zone.php`:
 * `Route::get('settings', ...)->middleware(ZonePermission::middleware(Permission::SettingsView))`; z kilkoma uprawnieniami
 * (`ZonePermission::middleware(A, B)`) wystarczy dowolne z nich.
 */
final class ZonePermission
{
    /**
     * Middleware z jednym uprawnieniem albo kilkoma — wtedy wystarczy dowolne z nich.
     */
    public static function middleware(BackedEnum|string ...$permissions): string
    {
        return RequireZonePermission::ALIAS . ':' . implode(',', array_map(static fn(BackedEnum|string $permission): string => $permission instanceof BackedEnum ? (string) $permission->value : $permission, $permissions));
    }
}
