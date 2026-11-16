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
use Simplysmart\Polygon\Contracts\HasLabel;

/**
 * Skąd jest kurs waluty: tabela A NBP (kurs średni) albo wpis ręczny administratora.
 */
enum ExchangeRateSource: string implements HasLabel
{
    case Nbp = 'nbp';
    case Manual = 'manual';

    public function label(): string
    {
        return PackageText::get('enums.exchange_rate_source.' . $this->value);
    }
}
