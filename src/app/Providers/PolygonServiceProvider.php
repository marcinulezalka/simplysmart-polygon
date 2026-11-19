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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Simplysmart\Polygon\Support\PolygonConfig;
use Simplysmart\Polygon\Console\Commands\IconPngCommand;
use Simplysmart\Polygon\Console\Commands\CreateAccountCommand;
use Simplysmart\Polygon\Console\Commands\ExportTranslationsCommand;
use Simplysmart\Polygon\Console\Commands\ImportTranslationsCommand;
use Simplysmart\Polygon\Console\Commands\InstallCommand;
use Simplysmart\Polygon\Console\Commands\RepairOverflowQueue;
use Simplysmart\Polygon\Console\Commands\SubsetIconsCommand;
use Simplysmart\Polygon\Console\Commands\SeedLocalization;
use Simplysmart\Polygon\Console\Commands\SeedOrganizations;

/**
 * Provider paczki Polygon, rejestrowany przez auto-discovery Laravela: scala config `polygon` (PolygonConfig),
 * rejestruje providery obszarów z `src/app/Providers/` (baza danych, tłumaczenia, konta, uprawnienia, assety — nowy obszar dostaje
 * własny provider zamiast rozbudowy tego) i komendy konsoli paczki.
 */
final class PolygonServiceProvider extends ServiceProvider
{
    /** Katalog `src/` paczki (config/, database/, lang/, resources/). */
    public const string ROOT = __DIR__ . '/../..';

    /**
     * Środowiska, w których leniwe ładowanie relacji przerywa żądanie (wyjątek) — testy wyłapują N+1; poza nimi
     * i produkcją (lokalnie, przed-produkcja) naruszenie trafia do logu, więc nie blokuje pracy na prawdziwych danych.
     */
    public const array STRICT_ENVIRONMENTS = ['testing'];

    /**
     * @var list<class-string<ServiceProvider>>
     */
    private const array AREAS = [
        DatabaseServiceProvider::class,
        TranslationServiceProvider::class,
        AccountsServiceProvider::class,
        PermissionsServiceProvider::class,
        ZonesServiceProvider::class,
        SignInServiceProvider::class,
        PolygonZoneServiceProvider::class,
        OrganizationsServiceProvider::class,
        AssetsServiceProvider::class,
        InvoicingServiceProvider::class,
        MailServiceProvider::class,
        EventLogServiceProvider::class,
    ];

    /**
     * @var list<class-string>
     */
    private const array COMMANDS = [
        SeedLocalization::class,
        SeedOrganizations::class,
        CreateAccountCommand::class,
        InstallCommand::class,
        ExportTranslationsCommand::class,
        ImportTranslationsCommand::class,
        RepairOverflowQueue::class,
        SubsetIconsCommand::class,
        IconPngCommand::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT . '/config/polygon.php', PolygonConfig::KEY);

        foreach (self::AREAS as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->environment('production'));

        if (! $this->app->environment(self::STRICT_ENVIRONMENTS)) {
            Model::handleLazyLoadingViolationUsing(static fn(Model $model, string $relation) => logger()->warning('Leniwe ładowanie relacji [' . $relation . '] modelu [' . $model::class . '].'));
        }

        if ($this->app->runningInConsole()) {
            $this->commands(self::COMMANDS);
        }
    }
}
