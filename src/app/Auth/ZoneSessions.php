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

namespace Simplysmart\Polygon\Auth;

use Illuminate\Database\DatabaseManager;
use Simplysmart\Polygon\Zones\ZoneDefinition;
use Simplysmart\Polygon\Zones\ZoneTable;

/**
 * Zapisane sesje strefy (sterownik `database`, tabela `<strefa>_sessions` albo z configu strefy).
 */
final readonly class ZoneSessions
{
    public function __construct(
        private DatabaseManager $connections,
    ) {}

    /**
     * Usuwa zapisane sesje konta; zwraca ich liczbę. Bez tabeli (inny sterownik sesji) nic nie robi:
     * te sesje i tak wylogowuje epoka konta.
     */
    public function forgetAccount(ZoneDefinition $zone, int|string $accountId): int
    {
        $table = $zone->session->table ?? ZoneTable::Sessions->for($zone->name);
        $connection = $this->connections->connection();

        if (! $connection->getSchemaBuilder()->hasTable($table)) {
            return 0;
        }

        return $connection->table($table)->where('user_id', $accountId)->delete();
    }
}
