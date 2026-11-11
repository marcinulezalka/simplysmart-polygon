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

namespace Simplysmart\Polygon\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Config\Repository as Config;
use Simplysmart\Polygon\Console\SendQueuedInvoices;
use Simplysmart\Polygon\Console\SyncInvoicing;
use Simplysmart\Polygon\Console\SyncInvoicingContractors;
use Simplysmart\Polygon\Invoicing\ContractorPushGate;
use Simplysmart\Polygon\Invoicing\InvoicingAccounts;
use Simplysmart\Polygon\Jobs\PushContractorJob;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\PolygonConfig;

/**
 * Faktury i integracja z systemem fakturowym (docs/baza-danych.md §21): bramka wysyłki kontrahentów jako singleton,
 * zlecenie wysyłki kontrahenta po jego zapisie (Jobs\PushContractorJob — tylko gdy organizacja ma aktywne konto
 * i bramka jest otwarta, czyli zapis nie pochodzi z synchronizacji), komendy konsoli i nocna synchronizacja
 * automatyczna (kontrahenci, faktury i pro formy) w harmonogramie o godzinie `polygon.invoicing.sync_at`.
 */
final class InvoicingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContractorPushGate::class);
    }

    public function boot(): void
    {
        Contractor::saved(function (Contractor $contractor): void {
            if ($this->app->make(ContractorPushGate::class)->isOpen() && $this->app->make(InvoicingAccounts::class)->forContractor($contractor) !== null) {
                PushContractorJob::dispatch($contractor->id);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SyncInvoicing::class, SyncInvoicingContractors::class, SendQueuedInvoices::class]);
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $time = $this->app->make(Config::class)->get(PolygonConfig::INVOICING_SYNC_AT);
                $schedule->command(SyncInvoicing::class)->dailyAt(is_string($time) && $time !== '' ? $time : PolygonConfig::INVOICING_SYNC_AT_DEFAULT)->withoutOverlapping();
            });
        }
    }
}
