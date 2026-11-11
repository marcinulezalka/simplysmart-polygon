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

namespace Simplysmart\Polygon\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\InvoiceStatus;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Invoicing\SendInvoice;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\Invoice;
use Throwable;

/**
 * Wysyłka faktury do systemu fakturowego (Invoicing\SendInvoice), zlecana po wystawieniu i po „Ponów wysyłkę”, po
 * zatwierdzeniu transakcji. Jedno zadanie na fakturę naraz (WithoutOverlapping po id faktury), więc dwie próby nie
 * wyślą jej równolegle. Błąd przejściowy ponawia kolejka (TRIES prób, BACKOFF); po wyczerpaniu prób faktura dostaje
 * stan „błąd wysyłki” (failed()) i czeka na ręczne ponowienie. Przy kolejce `sync` błąd przejściowy zostawia fakturę
 * w kolejce z komunikatem (dośle ją `polygon:invoicing:invoices-send` albo ponowienie), zamiast przerywać żądanie.
 */
final class SendInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 600, 1800, 3600];

    public function __construct(
        public readonly int $invoiceId,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('polygon.invoicing.invoice.' . $this->invoiceId))->releaseAfter(30)->expireAfter(300)];
    }

    public function handle(SendInvoice $send): void
    {
        $invoice = Invoice::query()->find($this->invoiceId);

        if ($invoice === null) {
            return;
        }

        try {
            $send->send($invoice);
        } catch (FakturowniaFailed $failed) {
            if ($this->retriedByQueue()) {
                throw $failed;
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $invoice = Invoice::query()->find($this->invoiceId);

        if ($invoice !== null && $invoice->status === InvoiceStatus::Queued) {
            $invoice->forceFill([ColumnNames::STATUS => InvoiceStatus::Failed, Invoice::ERROR => $invoice->error ?? PackageText::get('invoicing.send_failed')])->save();
        }
    }

    /**
     * Czy zadanie idzie przez prawdziwą kolejkę, która je ponowi — przy kolejce `sync` (np. lokalnie) wyjątek przerwałby
     * żądanie, które zleciło zadanie.
     */
    private function retriedByQueue(): bool
    {
        return $this->job !== null && ! $this->job instanceof SyncJob;
    }
}
