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

namespace Simplysmart\Polygon\Console\Commands;

use Illuminate\Console\Command;
use Simplysmart\Polygon\Enums\InvoicingSyncTrigger;
use Simplysmart\Polygon\Invoicing\InvoicingAccounts;
use Simplysmart\Polygon\Invoicing\SyncInvoicing as Synchronization;

/**
 * Automatyczna synchronizacja z systemem fakturowym (Invoicing\SyncInvoicing — kontrahenci, faktury i pro formy,
 * wysyłka kolejki): raz na dobę z harmonogramu (`polygon.invoicing.sync_at`, Providers\InvoicingServiceProvider)
 * dla aktywnych kont z włączoną synchronizacją automatyczną (`auto_sync`); `--force` obejmuje też konta z wyłączoną,
 * a token organizacji zawęża do jej konta. Błąd jednego konta nie przerywa pozostałych; komenda kończy się błędem,
 * gdy któreś konto zawiodło.
 */
final class SyncInvoicing extends Command
{
    protected $signature = 'polygon:invoicing:sync
        {organization? : Token organizacji; bez niego wszystkie konta}
        {--force : Także konta z wyłączoną synchronizacją automatyczną}';

    protected $description = 'Synchronizuje kontrahentów, faktury i pro formy z Fakturownią';

    public function handle(InvoicingAccounts $accounts, Synchronization $sync): int
    {
        $token = $this->argument('organization');
        $list = $accounts->activeFor(is_string($token) ? $token : null);

        if ($list === null) {
            $this->error('Organizacja nie ma aktywnego konta systemu fakturowego.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($list as $account) {
            if (! $account->auto_sync && $this->option('force') !== true) {
                continue;
            }

            $name = $account->organization?->contextName() ?? $account->name;
            $report = $sync->run($account, InvoicingSyncTrigger::Automatic);

            if (! $report->succeeded()) {
                $failed = true;
                $this->error("{$name}: {$report->error}");

                continue;
            }

            $contractors = $report->contractors;
            $invoices = $report->invoices;
            $this->info("{$name}: kontrahenci — nowi {$contractors->created}, zaktualizowani {$contractors->updated}, wysłani {$contractors->pushed}; faktury — nowe {$invoices->created}, zaktualizowane {$invoices->updated}, bez zmian {$invoices->unchanged}; wysyłka z kolejki {$report->invoicesQueued}.");

            foreach ([...$contractors->skipped, ...$invoices->skipped] as $entry) {
                $this->line("  - {$entry['name']}: {$entry['reason']}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
