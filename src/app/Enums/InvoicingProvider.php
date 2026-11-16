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
 * Dostawca systemu fakturowego konta organizacji (`invoicing_accounts.provider`, docs/baza-danych.md §21); na razie
 * tylko Fakturownia (Integrations\Fakturownia). Teksty: `enums.invoicing_provider.<value>`.
 */
enum InvoicingProvider: string
{
    case Fakturownia = 'fakturownia';

    public function label(): string
    {
        return PackageText::get('enums.invoicing_provider.' . $this->value);
    }
}
