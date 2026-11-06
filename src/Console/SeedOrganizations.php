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

use Simplysmart\Polygon\Database\Seeders\OrganizationsSeeder;

/**
 * Wczytuje dane referencyjne organizacji: domyślne typy jednostek i organizację hero (operatora aplikacji); zmiany
 * administratora zostają.
 */
final class SeedOrganizations extends SeedCommand
{
    protected $signature = 'polygon:organizations:seed';

    protected $description = 'Wczytuje typy jednostek organizacyjnych i organizację hero (Simplysmart)';

    protected function seeder(): string
    {
        return OrganizationsSeeder::class;
    }
}
