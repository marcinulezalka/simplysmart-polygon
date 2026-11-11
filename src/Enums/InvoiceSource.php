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
 * Pochodzenie faktury (`invoices.source`, docs/baza-danych.md §21): wystawiona w aplikacji i wysłana do systemu
 * fakturowego albo pobrana z Fakturowni (tylko do odczytu — nie jest wysyłana ani zmieniana w aplikacji).
 * Teksty: `enums.invoice_source.<value>`.
 */
enum InvoiceSource: string
{
    case Local = 'local';
    case Fakturownia = 'fakturownia';

    public function label(): string
    {
        return PackageText::get('enums.invoice_source.' . $this->value);
    }
}
