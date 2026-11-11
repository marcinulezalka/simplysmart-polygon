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

namespace Simplysmart\Polygon\Console;

use Illuminate\Console\Command;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\InvoiceStatus;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Invoicing\SendInvoice;
use Simplysmart\Polygon\Models\Invoice;

/**
 * Wysyła od razu (bez kolejki) faktury czekające w kolejce, a z `--failed` także te z błędem wysyłki — np. po awarii
 * workera kolejki albo po poprawieniu danych konta. Wysłana wcześniej faktura nie powstaje drugi raz (Invoicing\SendInvoice
 * szuka jej po `oid`).
 */
final class SendQueuedInvoices extends Command
{
    protected $signature = 'polygon:invoicing:invoices-send {--failed : Także faktury z błędem wysyłki}';

    protected $description = 'Wysyła do Fakturowni faktury czekające na wysyłkę';

    public function handle(SendInvoice $send): int
    {
        $statuses = [InvoiceStatus::Queued->value, ...($this->option('failed') === true ? [InvoiceStatus::Failed->value] : [])];
        $query = Invoice::query();
        $query->whereIn(ColumnNames::STATUS, $statuses);
        $query->orderBy(ColumnNames::ID);
        $sent = 0;
        $failed = 0;

        foreach ($query->get() as $invoice) {
            try {
                $result = $send->send($invoice);
            } catch (FakturowniaFailed $exception) {
                $failed++;
                $this->error("{$invoice->name}: {$exception->getMessage()}");

                continue;
            }

            if ($result->status === InvoiceStatus::Sent) {
                $sent++;
                $this->line("{$invoice->name}: wysłana.");
            } else {
                $failed++;
                $this->error("{$invoice->name}: {$result->error}");
            }
        }

        $this->info("Wysłane: {$sent}, z błędem: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
