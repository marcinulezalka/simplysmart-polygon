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

namespace Simplysmart\Polygon\Integrations\Fakturownia;

use Carbon\CarbonImmutable;

/**
 * Faktura odczytana z Fakturowni (RemoteInvoiceMapper::read()): identyfikatory (`externalId` — id w Fakturowni,
 * `clientId` — id klienta nabywcy, `oid` — nasz token, gdy fakturę wystawiła aplikacja), `updatedAt` (ostatnia zmiana
 * w Fakturowni), `buyer` (nabywca jako obiekt klienta Fakturowni — dla ClientMapper, gdy trzeba założyć kontrahenta),
 * `attributes` (kolumny `invoices`) i `items` (kolumny `invoice_items` w kolejności pozycji).
 */
final readonly class RemoteInvoice
{
    /**
     * @param array<string, mixed> $buyer
     * @param array<string, mixed> $attributes
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        public string $externalId,
        public ?string $clientId,
        public ?string $oid,
        public ?CarbonImmutable $updatedAt,
        public array $buyer,
        public array $attributes,
        public array $items,
    ) {}
}
