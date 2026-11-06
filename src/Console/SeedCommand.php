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

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;

/**
 * Baza komend `polygon:<obszar>:seed`: uruchamia jeden seeder referencyjny przez `db:seed --force` (idempotentny,
 * wolno go uruchomić ponownie i na produkcji). Komenda podaje tylko sygnaturę, opis i seeder().
 */
abstract class SeedCommand extends Command
{
    public function handle(): int
    {
        return $this->call('db:seed', ['--class' => $this->seeder(), '--force' => true]);
    }

    /**
     * @return class-string<Seeder>
     */
    abstract protected function seeder(): string;
}
