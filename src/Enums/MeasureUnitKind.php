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

namespace Simplysmart\Polygon\Enums;

use Simplysmart\Polygon\Localization\PackageText;

/**
 * Rodzaj jednostki miary (kolumna `kind` słownika `locale_measure_units`): liczba sztuk, masa, długość, powierzchnia,
 * objętość, czas, energia, usługa i opakowanie. Teksty: `enums.measure_unit_kind.<value>`.
 */
enum MeasureUnitKind: string
{
    case Count = 'count';
    case Mass = 'mass';
    case Length = 'length';
    case Area = 'area';
    case Volume = 'volume';
    case Time = 'time';
    case Energy = 'energy';
    case Service = 'service';
    case Packaging = 'packaging';

    public function label(): string
    {
        return PackageText::get('enums.measure_unit_kind.' . $this->value);
    }
}
