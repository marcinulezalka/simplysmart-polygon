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
 * Opodatkowanie pozycji faktury (`invoice_items.tax`): stawka z procentem (`tax_percent`), zwolnienie („zw”) albo
 * „nie podlega” („np”) — dwie ostatnie bez procentu i bez kwoty VAT. Teksty: `enums.invoice_tax.<value>`.
 */
enum InvoiceTax: string
{
    case Rate = 'rate';
    case Exempt = 'exempt';
    case NotApplicable = 'not_applicable';

    /**
     * Czy pozycja ma procent stawki i kwotę VAT.
     */
    public function hasPercentage(): bool
    {
        return $this === self::Rate;
    }

    public function label(): string
    {
        return PackageText::get('enums.invoice_tax.' . $this->value);
    }
}
