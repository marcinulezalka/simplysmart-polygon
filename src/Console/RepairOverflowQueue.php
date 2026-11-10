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

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Simplysmart\Polygon\Calendar\OverflowQueueRepair;

/**
 * Jednorazowo po wdrożeniu kolejki nadliczbowych: nadaje własne godziny rezerwacjom nadliczbowym od dziś, zapisanym
 * jeszcze na samym końcu bloku (Calendar\OverflowQueueRepair). Uruchomiona ponownie niczego nie zmienia.
 */
final class RepairOverflowQueue extends Command
{
    protected $signature = 'polygon:calendar:overflow-repair';

    protected $description = 'Nadaje kolejne godziny zapisanym wcześniej rezerwacjom nadliczbowym (od dziś)';

    public function handle(OverflowQueueRepair $repair): int
    {
        $result = $repair->from(CarbonImmutable::today());
        $this->info("Nowe godziny: {$result['repaired']}, pominięte (bez bloku grafiku): {$result['skipped']}.");

        return self::SUCCESS;
    }
}
