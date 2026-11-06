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
 * Dane referencyjne organizacji: typy jednostek, potem organizacja hero (operatora aplikacji).
 */
final class OrganizationsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([OrganizationUnitTypeSeeder::class, HeroOrganizationSeeder::class]);
    }
}
