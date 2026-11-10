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

namespace Simplysmart\Polygon;

use Illuminate\Support\ServiceProvider;
use Simplysmart\Polygon\Console\IconPngCommand;
use Simplysmart\Polygon\Console\CreateAccountCommand;
use Simplysmart\Polygon\Console\ExportTranslationsCommand;
use Simplysmart\Polygon\Console\ImportTranslationsCommand;
use Simplysmart\Polygon\Console\InstallCommand;
use Simplysmart\Polygon\Console\RepairOverflowQueue;
use Simplysmart\Polygon\Console\SubsetIconsCommand;
use Simplysmart\Polygon\Console\SeedLocalization;
use Simplysmart\Polygon\Console\SeedOrganizations;
use Simplysmart\Polygon\Providers\AccountsServiceProvider;
use Simplysmart\Polygon\Providers\AssetsServiceProvider;
use Simplysmart\Polygon\Providers\DatabaseServiceProvider;
use Simplysmart\Polygon\Providers\OrganizationsServiceProvider;
use Simplysmart\Polygon\Providers\PermissionsServiceProvider;
use Simplysmart\Polygon\Providers\PolygonZoneServiceProvider;
use Simplysmart\Polygon\Providers\SignInServiceProvider;
use Simplysmart\Polygon\Providers\TranslationServiceProvider;
use Simplysmart\Polygon\Providers\ZonesServiceProvider;

/**
 * Provider paczki Polygon, rejestrowany przez auto-discovery Laravela: scala config `polygon` (PolygonConfig),
 * rejestruje providery obszarów z `src/Providers/` (baza danych, tłumaczenia, konta, uprawnienia, assety — nowy obszar dostaje
 * własny provider zamiast rozbudowy tego) i komendy konsoli paczki.
 */
final class PolygonServiceProvider extends ServiceProvider
{
    /** Katalog główny paczki (config/, database/, lang/). */
    public const string ROOT = __DIR__ . '/..';

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
        if ($this->app->runningInConsole()) {
            $this->commands(self::COMMANDS);
        }
    }
}
