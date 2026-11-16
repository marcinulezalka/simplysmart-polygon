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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Simplysmart\Polygon\Enums\InvoicingSyncTrigger;
use Simplysmart\Polygon\Invoicing\SyncInvoicing;
use Simplysmart\Polygon\Models\InvoicingAccount;

/**
 * Synchronizacja konta z systemem fakturowym na żądanie (przycisk „Synchronizuj teraz” w strefie): Invoicing\SyncInvoicing
 * w kolejce — przy kolejce `sync` od razu w żądaniu, przy `database` w tle u workera. Jedna synchronizacja konta naraz
 * (drugie zlecenie w trakcie jest pomijane); bez ponowień — wynik i błąd są w raporcie konta, a następną próbą jest
 * kolejne kliknięcie albo nocna synchronizacja. Konto wyłączone albo usunięte — nic nie robi.
 */
final class SyncInvoicingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Limit czasu w sekundach — pierwsze pobranie faktur z dwóch lat może trwać kilka minut. */
    public int $timeout = 900;

    public function __construct(
        public readonly int $accountId,
        public readonly InvoicingSyncTrigger $trigger = InvoicingSyncTrigger::Manual,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('polygon.invoicing.sync.' . $this->accountId))->dontRelease()->expireAfter($this->timeout)];
    }

    public function handle(SyncInvoicing $sync): void
    {
        $account = InvoicingAccount::query()->find($this->accountId);

        if ($account === null || ! $account->is_active) {
            return;
        }

        $sync->run($account, $this->trigger);
    }
}
