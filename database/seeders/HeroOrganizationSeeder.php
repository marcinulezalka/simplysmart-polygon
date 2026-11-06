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
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;

/**
 * Organizacja hero — operator aplikacji (stałe id Organization::HERO_ID, nazwa Organization::HERO_NAME), w
 * OrganizationsSeeder (`db:seed`, `polygon:organizations:seed`). Seeder referencyjny, idempotentny: przywraca usuniętą i wyłączoną,
 * a zmienionej nazwy nie nadpisuje.
 */
final class HeroOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::withTrashed()->find(Organization::HERO_ID);

        if ($organization === null) {
            $organization = new Organization();
            $organization->forceFill([
                ColumnNames::ID => Organization::HERO_ID,
                ColumnNames::NAME => Organization::HERO_NAME,
                ColumnNames::IS_DEFAULT => true,
            ]);
        }

        $organization->forceFill([ColumnNames::IS_ACTIVE => true, ColumnNames::DELETED_AT => null])->save();
    }
}
