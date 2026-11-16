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

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Localization\PackageText;

/**
 * Stan umowy z kontrahentem w danym dniu, liczony z okresu obowiązywania: przyszła (przed `valid_from`), obowiązuje,
 * wygasła (po `valid_to`). Teksty: `enums.contract_status.<value>`.
 */
enum ContractStatus: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Expired = 'expired';

    public static function of(CarbonImmutable $validFrom, ?CarbonImmutable $validTo, CarbonImmutable $day): self
    {
        $day = $day->startOfDay();

        return match (true) {
            $day->lessThan($validFrom->startOfDay()) => self::Upcoming,
            $validTo !== null && $day->greaterThan($validTo->startOfDay()) => self::Expired,
            default => self::Active,
        };
    }

    public function label(): string
    {
        return PackageText::get('enums.contract_status.' . $this->value);
    }
}
