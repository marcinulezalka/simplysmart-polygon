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
 * Poziom udostępnienia kalendarza najemcy właścicielowi zasobu w umowie najmu (Models\ResourceLease): zapis wizyt
 * w wynajętych godzinach (`book`, zawiera podgląd), podgląd grafiku i zapisów tylko do odczytu (`view`) oraz publiczna
 * tablica online (`wallboard`, bez danych osób). Teksty: `enums.lease_access.<value>`.
 */
enum LeaseAccess: string
{
    case Book = 'book';
    case View = 'view';
    case Wallboard = 'wallboard';

    /**
     * Czy ten poziom daje $access (zapis daje też podgląd).
     */
    public function grants(self $access): bool
    {
        return $this === $access || ($this === self::Book && $access === self::View);
    }

    public function label(): string
    {
        return PackageText::get('enums.lease_access.' . $this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
