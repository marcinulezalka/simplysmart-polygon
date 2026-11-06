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

use Simplysmart\Polygon\Database\Seeders\LocalizationSeeder;

/**
 * Wczytuje słowniki lokalizacji (waluty, języki, kraje, stawki VAT, jednostki miar, dni wolne, kursy walut); wybory
 * administratora zostają.
 */
final class SeedLocalization extends SeedCommand
{
    protected $signature = 'polygon:localization:seed';

    protected $description = 'Wczytuje słowniki lokalizacji: waluty, języki, kraje i stawki VAT';

    protected function seeder(): string
    {
        return LocalizationSeeder::class;
    }
}
