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
 * Rodzaj faktury (`invoices.kind`): faktura VAT albo pro forma. Wartości są zgodne z polem `kind` API Fakturowni.
 * Teksty: `enums.invoice_kind.<value>`.
 */
enum InvoiceKind: string
{
    case Vat = 'vat';
    case Proforma = 'proforma';

    public function label(): string
    {
        return PackageText::get('enums.invoice_kind.' . $this->value);
    }
}
