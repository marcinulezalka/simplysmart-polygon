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

namespace Simplysmart\Polygon\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Słowniki lokalizacji w kolejności zależności: waluty i języki, potem kraje (linkują je), potem stawki VAT, jednostki
 * miar i dni wolne krajów oraz kursy walut.
 */
final class LocalizationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CurrenciesSeeder::class, LanguagesSeeder::class, CountriesSeeder::class, TaxRatesSeeder::class, MeasureUnitsSeeder::class, HolidaysSeeder::class, ExchangeRatesSeeder::class]);
    }
}
