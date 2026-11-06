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

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Simplysmart\Polygon\Database\Seeders\DatabaseSeeder;
use Simplysmart\Polygon\Http\Middleware\RecordHistoryActor;
use Simplysmart\Polygon\Listeners\SetHistoryActorForJob;
use Simplysmart\Polygon\PolygonServiceProvider;

/**
 * Baza danych paczki (docs/baza-danych.md): migracje `database/migrations/`, seeder główny pod nazwą `DatabaseSeeder`
 * (projekt nie ma własnego) i aktor historii zmian — middleware na końcu grupy `web` oraz listener zadań kolejki.
 */
final class DatabaseServiceProvider extends ServiceProvider
{
    private const string WEB_GROUP = 'web';

    public function register(): void
    {
        $this->app->bindIf(DatabaseSeeder::CONTAINER_NAME, DatabaseSeeder::class);
    }

    public function boot(Router $router, Dispatcher $events): void
    {
        $this->loadMigrationsFrom(PolygonServiceProvider::ROOT . '/database/migrations');
        $router->pushMiddlewareToGroup(self::WEB_GROUP, RecordHistoryActor::class);
        $events->listen(JobProcessing::class, [SetHistoryActorForJob::class, 'start']);
        $events->listen([JobProcessed::class, JobFailed::class], [SetHistoryActorForJob::class, 'finish']);
    }
}
