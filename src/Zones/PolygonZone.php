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

use Simplysmart\Polygon\Models\PolygonAccount;

/**
 * Strefa `polygon` (konta w `polygon_accounts`, migracja `create_polygon_tables`): nazwa, guard sesyjny i provider
 * Eloquent dopisywane do config/auth przez PolygonServiceProvider, jak strefy admiro (bez zmian w config/auth.php).
 * Tabela `users` zostaje dla kont frontendu (guard `web`).
 */
final class PolygonZone
{
    public const string NAME = 'polygon';

    public const string GUARD = self::NAME;

    public const string PROVIDER = 'polygon_accounts';

    /**
     * Klucze config/auth guarda i providera strefy.
     *
     * @return array<string, array<string, string>>
     */
    public static function authConfig(): array
    {
        return [
            'auth.guards.' . self::GUARD => ['driver' => 'session', 'provider' => self::PROVIDER],
            'auth.providers.' . self::PROVIDER => ['driver' => 'eloquent', 'model' => PolygonAccount::class],
        ];
    }
}
