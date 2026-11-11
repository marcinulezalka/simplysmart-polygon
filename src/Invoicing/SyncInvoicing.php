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
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\InvoiceSource;
use Simplysmart\Polygon\Enums\InvoiceStatus;
use Simplysmart\Polygon\Enums\InvoicingSyncTrigger;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Jobs\SendInvoiceJob;
use Simplysmart\Polygon\Models\Invoice;
use Simplysmart\Polygon\Models\InvoicingAccount;
use Simplysmart\Polygon\Models\Organization;

/**
 * Pełna synchronizacja konta z systemem fakturowym (docs/baza-danych.md §21), ta sama w obu trybach — automatycznym
 * (nocny harmonogram, Console\SyncInvoicing) i na żądanie (przycisk na ekranie, Jobs\SyncInvoicingJob): kontrahenci
 * w obie strony (SyncContractors::sync()), potem faktury VAT i pro forma z Fakturowni (ImportInvoices), na koniec
 * zlecenie wysyłki faktur, które czekają w kolejce. Raport (InvoicingSyncReport) trafia do `last_sync_report` konta
 * także po błędzie, który przerwał synchronizację — wtedy z komunikatem błędu.
 */
final readonly class SyncInvoicing
{
    public function __construct(
        private InvoicingAccounts $accounts,
        private SyncContractors $contractors,
        private ImportInvoices $invoices,
    ) {}

    public function run(InvoicingAccount $account, InvoicingSyncTrigger $trigger): InvoicingSyncReport
    {
        $report = new InvoicingSyncReport($trigger, CarbonImmutable::now());

        try {
            $report->contractors = $this->contractors->sync($account);
            $this->invoices->import($account, $report->invoices);
            $report->invoicesQueued = $this->sendQueued($account);
        } catch (FakturowniaFailed $failed) {
            $report->error = $failed->getMessage();
        }

        $report->finishedAt = CarbonImmutable::now();
        $account->forceFill([InvoicingAccount::LAST_SYNC_REPORT => $report->toArray()])->save();

        return $report;
    }

    /**
     * Zleca wysyłkę faktur wystawionych w aplikacji, które czekają w kolejce; zwraca ich liczbę.
     */
    private function sendQueued(InvoicingAccount $account): int
    {
        $query = Invoice::query();
        $query->whereIn(Organization::FOREIGN_KEY, $this->accounts->organizationIds($account));
        $query->where(ColumnNames::STATUS, InvoiceStatus::Queued->value);
        $query->where(Invoice::SOURCE, InvoiceSource::Local->value);
        $invoices = $query->get([ColumnNames::ID]);

        foreach ($invoices as $invoice) {
            SendInvoiceJob::dispatch($invoice->id);
        }

        return $invoices->count();
    }
}
