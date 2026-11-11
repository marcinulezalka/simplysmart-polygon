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

use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaClients;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Models\Invoice;

/**
 * PDF wysłanej faktury pobrany z Fakturowni przez serwer (po `external_id`) — token API nie trafia do przeglądarki.
 * Faktura niewysłana albo organizacja bez konta: null.
 */
final readonly class InvoicePdf
{
    public function __construct(
        private InvoicingAccounts $accounts,
        private FakturowniaClients $clients,
    ) {}

    /**
     * @throws FakturowniaFailed
     */
    public function of(Invoice $invoice): ?string
    {
        $account = $invoice->organization === null ? null : $this->accounts->of($invoice->organization);

        return $account === null || $invoice->external_id === null ? null : $this->clients->for($account)->invoicePdf($invoice->external_id);
    }
}
