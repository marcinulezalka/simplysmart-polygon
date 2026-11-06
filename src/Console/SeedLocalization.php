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
use Simplysmart\Polygon\Database\Seeders\LocalizationSeeder;

/**
 * Wczytuje słowniki lokalizacji (waluty, języki, kraje, stawki VAT). Seeder referencyjny, idempotentny:
 * wolno go uruchomić ponownie i na produkcji, wybory administratora zostają.
 */
final class SeedLocalization extends Command
{
    protected $signature = 'polygon:localization:seed';

    protected $description = 'Wczytuje słowniki lokalizacji: waluty, języki, kraje i stawki VAT';

    public function handle(): int
    {
        return $this->call('db:seed', ['--class' => LocalizationSeeder::class, '--force' => true]);
    }
}
