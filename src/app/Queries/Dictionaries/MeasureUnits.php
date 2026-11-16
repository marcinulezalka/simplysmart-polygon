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

namespace Simplysmart\Polygon\Queries\Dictionaries;

use Illuminate\Database\Eloquent\Collection;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Dictionaries\Country;
use Simplysmart\Polygon\Models\Dictionaries\MeasureUnit;

/**
 * Odczyt jednostek miar krajów (słownik `locale_measure_units`) w kolejności słownika.
 */
final class MeasureUnits
{
    /**
     * Jednostki kraju (także wyłączone), w kolejności `sort_order`, potem nazwy.
     *
     * @return Collection<int, MeasureUnit>
     */
    public function forCountry(Country $country): Collection
    {
        $query = MeasureUnit::query();
        $query->where('country_id', $country->id);
        $query->orderBy(ColumnNames::SORT_ORDER);
        $query->orderBy(ColumnNames::NAME);

        return $query->get();
    }
}
