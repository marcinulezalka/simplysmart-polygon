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

namespace Simplysmart\Polygon\Install;

use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\MainModel;

/**
 * Atrybuty z pliku instalacji (`attributes` organizacji i konta) w `json_attributes` rekordu: klucze z pliku zastępują
 * te same klucze rekordu (całą wartość), pozostałe klucze rekordu zostają. Zapis tylko przy zmianie — przy ponownej
 * instalacji rekord bez zmian nie dostaje nowej wersji w historii.
 */
final readonly class InstallAttributes
{
    /**
     * @param array<string, mixed> $attributes
     *
     * @return bool Czy rekord się zmienił.
     */
    public function merge(MainModel $model, array $attributes): bool
    {
        if ($attributes === []) {
            return false;
        }

        $current = $model->getAttribute(ColumnNames::JSON_ATTRIBUTES);
        $current = is_array($current) ? $current : [];
        $merged = [...$current, ...$attributes];

        if ($merged == $current) {
            return false;
        }

        $model->forceFill([ColumnNames::JSON_ATTRIBUTES => $merged])->save();

        return true;
    }
}
