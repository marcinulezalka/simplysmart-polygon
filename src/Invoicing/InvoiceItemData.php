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

namespace Simplysmart\Polygon\Invoicing;

use Simplysmart\Polygon\Enums\InvoiceTax;

/**
 * Pozycja wystawianej faktury z formularza: nazwa, ilość i cena jednostkowa netto jako teksty dziesiętne (kropka albo
 * przecinek), jednostka, opodatkowanie z procentem stawki (bez procentu dla „zw” i „np”) i opcjonalne id stawki słownika.
 */
final readonly class InvoiceItemData
{
    public function __construct(
        public string $name,
        public string $quantity,
        public string $unitPriceNet,
        public InvoiceTax $tax = InvoiceTax::Rate,
        public ?string $taxPercent = null,
        public ?string $unit = null,
        public ?int $taxRateId = null,
    ) {}
}
