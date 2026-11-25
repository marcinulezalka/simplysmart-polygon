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
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Invoicing\InvoicingAccounts;
use Simplysmart\Polygon\Invoicing\SyncContractors;

/**
 * Synchronizuje kontrahentów z systemem fakturowym (Invoicing\SyncContractors::sync()): wszystkich aktywnych kont albo
 * konta organizacji o podanym tokenie. Uruchamiana ręcznie — harmonogram uruchamia pełną synchronizację
 * (Console\Commands\SyncInvoicing: kontrahenci, faktury i pro formy). Błąd jednego konta nie przerywa pozostałych; komenda kończy się błędem, gdy któreś konto zawiodło.
 */
final class SyncInvoicingContractors extends Command
{
    protected $signature = 'polygon:invoicing:contractors-sync {organization? : Token organizacji; bez niego wszystkie konta}';

    protected $description = 'Synchronizuje kontrahentów organizacji z klientami w Fakturowni';

    public function handle(InvoicingAccounts $accounts, SyncContractors $sync): int
    {
        $token = $this->argument('organization');
        $list = $accounts->activeFor(is_string($token) ? $token : null);

        if ($list === null) {
            $this->error('Organizacja nie ma aktywnego konta systemu fakturowego.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($list as $account) {
            $name = $account->organization?->contextName() ?? $account->name;

            try {
                $report = $sync->sync($account);
            } catch (FakturowniaFailed $exception) {
                $failed = true;
                $this->error("{$name}: {$exception->getMessage()}");

                continue;
            }

            $skipped = count($report->skipped);
            $this->info("{$name}: nowi {$report->created}, zaktualizowani {$report->updated}, wysłani {$report->pushed}, bez zmian {$report->unchanged}, pominięci {$skipped}.");

            foreach ($report->skipped as $entry) {
                $this->line("  - {$entry['name']}: {$entry['reason']}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
