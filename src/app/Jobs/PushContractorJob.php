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
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Invoicing\InvoicingAccounts;
use Simplysmart\Polygon\Invoicing\SyncContractors;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\InvoicingAccount;

/**
 * Wysyłka zapisanego kontrahenta do systemu fakturowego (zlecana po zapisie kontrahenta — Providers\InvoicingServiceProvider,
 * po zatwierdzeniu transakcji). Bez konta organizacji albo usuniętego kontrahenta nic nie robi. Brak połączenia, limit
 * żądań i błąd serwera ponawia kolejka (TRIES prób, BACKOFF); inna odmowa — a przy kolejce `sync` każda — trafia do
 * `last_error` konta i zadanie się kończy — kontrahenta dośle następna synchronizacja. Jedno zadanie na kontrahenta naraz.
 */
final class PushContractorJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public readonly int $contractorId,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('polygon.invoicing.contractor.' . $this->contractorId))->releaseAfter(30)->expireAfter(300)];
    }

    public function handle(InvoicingAccounts $accounts, SyncContractors $sync): void
    {
        $contractor = Contractor::query()->find($this->contractorId);
        $account = $contractor === null ? null : $accounts->forContractor($contractor);

        if ($contractor === null || $account === null || $contractor->isInvoicingLocalOnly()) {
            return;
        }

        try {
            $sync->push($account, $contractor);
        } catch (FakturowniaFailed $failed) {
            if ($failed->retryable() && $this->retriedByQueue()) {
                throw $failed;
            }

            $account->forceFill([InvoicingAccount::LAST_ERROR => $contractor->displayName() . ': ' . $failed->getMessage()])->save();
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
