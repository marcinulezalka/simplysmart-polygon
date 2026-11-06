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

namespace Simplysmart\Polygon\Models;

use Simplysmart\Polygon\Zones\PolygonZone;

/**
 * Konto strefy `polygon` (tabela `polygon_accounts` z migracji `create_polygon_tables`), jak `AdmiroAccount`
 * w admiro: kolumny standardowe, e-mail szyfrowany z `email_hash`, role i uprawnienia spatie w guardzie strefy.
 * Konta frontendu są w `users` (Models\User).
 */
final class PolygonAccount extends ZoneAccount
{
    public const string TABLE = PolygonZone::PROVIDER;

    protected $table = self::TABLE;

    protected string $guard_name = PolygonZone::GUARD;
}
