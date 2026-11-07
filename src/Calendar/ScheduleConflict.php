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

use Simplysmart\Polygon\Localization\PackageText;

/**
 * Powód odrzucenia grafiku zasobu (Calendar\ScheduleConflicts): błędny przedział, zasób niedostępny dla jednostki,
 * zasób zajęty przez inny grafik, wykonawca w tym czasie gdzie indziej, zasób właściciela wynajęty wtedy innej
 * organizacji (LeasedOut) albo grafik najemcy poza oknami lub okresem umowy najmu (OutsideLease). Paczka strefy może dołożyć własne powody
 * (np. konto bez roli lekarza) przez ScheduleRejected::because(). Teksty: `calendar.conflict.<value>`.
 */
enum ScheduleConflict: string
{
    case InvalidRange = 'invalid_range';
    case ResourceUnavailable = 'resource_unavailable';
    case ResourceTaken = 'resource_taken';
    case PerformerBusy = 'performer_busy';
    case LeasedOut = 'leased_out';
    case OutsideLease = 'outside_lease';

    public function label(): string
    {
        return PackageText::get('calendar.conflict.' . $this->value);
    }
}
