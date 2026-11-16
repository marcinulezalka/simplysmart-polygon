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
 * Tryb umowy najmu: `windows` — okna godzinowe na wyłączność (właściciel nie układa w nich grafiku, najemca mieści się
 * w oknach), `on_demand` — najem na żądanie w wolnym czasie zasobu: okna wyznaczają tylko godziny, w których najemca
 * może korzystać, bez wyłączności, a jego grafik wypełnia wolny czas (ResourceSchedule::FILLS_GAPS); wizyty liczy się do
 * rozliczenia. Teksty: `enums.lease_mode.<value>`.
 */
enum LeaseMode: string
{
    case Windows = 'windows';
    case OnDemand = 'on_demand';

    public function isExclusive(): bool
    {
        return $this === self::Windows;
    }

    public function label(): string
    {
        return PackageText::get('enums.lease_mode.' . $this->value);
    }
}
