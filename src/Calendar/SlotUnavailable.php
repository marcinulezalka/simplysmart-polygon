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

namespace Simplysmart\Polygon\Calendar;

use RuntimeException;
use Simplysmart\Polygon\Localization\PackageText;

/**
 * Termin nie jest wolnym slotem wykonawcy w jednostce (zajęty — także przez równoległą rezerwację —, miniony, poza
 * grafikiem albo w dzień wolny); rezerwacja nie powstała.
 */
final class SlotUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(PackageText::get('calendar.slot_unavailable'));
    }
}
