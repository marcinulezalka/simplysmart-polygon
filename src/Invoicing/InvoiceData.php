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

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Enums\InvoiceKind;
use Simplysmart\Polygon\Enums\PaymentMethod;

/**
 * Dane wystawianej faktury z formularza: daty wystawienia i sprzedaży, termin i forma płatności, rodzaj, waluta
 * (null — waluta aplikacji), uwagi i pozycje.
 */
final readonly class InvoiceData
{
    /**
     * @param list<InvoiceItemData> $items
     */
    public function __construct(
        public CarbonImmutable $issueDate,
        public CarbonImmutable $sellDate,
        public array $items,
        public ?CarbonImmutable $paymentDue = null,
        public PaymentMethod $paymentMethod = PaymentMethod::Transfer,
        public InvoiceKind $kind = InvoiceKind::Vat,
        public ?string $currencyCode = null,
        public ?string $notes = null,
    ) {}
}
