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

namespace Simplysmart\Polygon\Queries\Organizations;

use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;

/**
 * Aktywne (włączone i nieusunięte) organizacje po tokenie z formularza, np. wybór kontekstu konta strefy.
 */
final class ActiveOrganizations
{
    public function findByToken(string $token): ?Organization
    {
        $query = Organization::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->where(ColumnNames::TOKEN, $token);

        return $query->first();
    }
}
