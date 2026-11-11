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
 * Forma płatności faktury (`invoices.payment_method`): przelew, gotówka albo karta. Wartości są zgodne z polem
 * `payment_type` API Fakturowni. Teksty: `enums.payment_method.<value>`.
 */
enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Card = 'card';

    public function label(): string
    {
        return PackageText::get('enums.payment_method.' . $this->value);
    }
}
