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
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Invoicing\InvoicingAccounts;
use Simplysmart\Polygon\Invoicing\SyncContractors;
use Simplysmart\Polygon\Models\Organization;

/**
 * Synchronizuje kontrahentów z systemem fakturowym (Invoicing\SyncContractors::sync()): wszystkich aktywnych kont albo
 * konta organizacji o podanym tokenie. Uruchamiana co godzinę z harmonogramu (Providers\InvoicingServiceProvider)
 * i ręcznie. Błąd jednego konta nie przerywa pozostałych; komenda kończy się błędem, gdy któreś konto zawiodło.
 */
final class SyncInvoicingContractors extends Command
{
    protected $signature = 'polygon:invoicing:contractors-sync {organization? : Token organizacji; bez niego wszystkie konta}';

    protected $description = 'Synchronizuje kontrahentów organizacji z klientami w Fakturowni';

    public function handle(InvoicingAccounts $accounts, SyncContractors $sync): int
    {
        $token = $this->argument('organization');
        $list = $accounts->active();

        if (is_string($token) && $token !== '') {
            $organization = Organization::query()->where(ColumnNames::TOKEN, $token)->first();
            $account = $organization === null ? null : $accounts->of($organization);

            if ($account === null) {
                $this->error('Organizacja nie ma aktywnego konta systemu fakturowego.');

                return self::FAILURE;
            }

            $list = $list->where(ColumnNames::ID, $account->id);
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
