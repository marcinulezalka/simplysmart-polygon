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

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Simplysmart\Polygon\Contracts\BlindIndex;
use Simplysmart\Polygon\Contracts\InterfaceLocales;
use Simplysmart\Polygon\Contracts\LocalFormatter;
use Simplysmart\Polygon\Contracts\TextTranslator;
use Simplysmart\Polygon\Console\CreateAccountCommand;
use Simplysmart\Polygon\Console\ExportTranslationsCommand;
use Simplysmart\Polygon\Console\ImportTranslationsCommand;
use Simplysmart\Polygon\Console\InstallCommand;
use Simplysmart\Polygon\Console\SeedLocalization;
use Simplysmart\Polygon\Database\Seeders\DatabaseSeeder;
use Simplysmart\Polygon\Http\Middleware\RecordHistoryActor;
use Simplysmart\Polygon\Listeners\SetHistoryActorForJob;
use Simplysmart\Polygon\Localization\DictionaryInterfaceLocales;
use Simplysmart\Polygon\Localization\IntlLocalFormatter;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\User;
use Simplysmart\Polygon\Permissions\PermissionDefaults;
use Simplysmart\Polygon\Security\HmacBlindIndex;
use Simplysmart\Polygon\Translation\CascadeTextTranslator;
use Simplysmart\Polygon\Translation\CoreTranslationPath;
use Simplysmart\Polygon\Zones\PolygonZone;

/**
 * Provider paczki Polygon, rejestrowany przez auto-discovery Laravela: config `polygon`, konfiguracja
 * spatie/laravel-permission (PermissionDefaults), model użytkowników `users` (Models\User, gdy projekt nie ma
 * skonfigurowanego modelu), seeder główny `DatabaseSeeder`, migracje bazy (`database/migrations/`,
 * docs/baza-danych.md), tłumaczenia (kaskada TextTranslator, helpery text_* i local_*, polskie teksty frameworka
 * w lang/ paczki między frameworkiem a lang/ projektu — CoreTranslationPath), aktor historii zmian (middleware grupy `web`, zadania kolejki), teksty `polygon::` i komendy
 * `polygon:localization:seed`, `polygon:account:create`, `polygon:install` (pliki instalacji JSON); guard i provider strefy `polygon` (konta w `polygon_accounts`)
 * oraz indeks e-maili kont (BlindIndex, HMAC z APP_KEY).
 */
final class PolygonServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__ . '/..';

    private const string USERS_MODEL = 'auth.providers.users.model';

    private const string WEB_GROUP = 'web';

    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT . '/config/polygon.php', 'polygon');
        $this->app->bindIf(DatabaseSeeder::CONTAINER_NAME, DatabaseSeeder::class);
        $this->app->extend('translation.loader', fn(Loader $loader): Loader => (new CoreTranslationPath(
            $this->app->make(Filesystem::class),
            self::ROOT . '/lang',
            $this->app->langPath(),
        ))->extend($loader));
        $this->app->bind(TextTranslator::class, CascadeTextTranslator::class);
        $this->app->bind(LocalFormatter::class, IntlLocalFormatter::class);
        $this->app->bind(InterfaceLocales::class, DictionaryInterfaceLocales::class);
        $this->app->singleton(BlindIndex::class, static fn(Application $app): BlindIndex => new HmacBlindIndex(self::appKey($app->make(Repository::class))));
        $this->app->booting(function (): void {
            $this->app->make(Repository::class)->set(PolygonZone::authConfig());
            $this->app->make(PermissionDefaults::class)->apply();
            $this->useUserModel($this->app->make(Repository::class));
        });
    }

    public function boot(Router $router, Dispatcher $events): void
    {
        $router->pushMiddlewareToGroup(self::WEB_GROUP, RecordHistoryActor::class);
        $events->listen(JobProcessing::class, [SetHistoryActorForJob::class, 'start']);
        $events->listen([JobProcessed::class, JobFailed::class], [SetHistoryActorForJob::class, 'finish']);
        $this->loadMigrationsFrom(self::ROOT . '/database/migrations');
        $this->loadTranslationsFrom(self::ROOT . '/lang', PackageText::NAMESPACE);

        if ($this->app->runningInConsole()) {
            $this->commands([SeedLocalization::class, CreateAccountCommand::class, InstallCommand::class, ExportTranslationsCommand::class, ImportTranslationsCommand::class]);
        }
    }

    /**
     * Model providera `users`: Models\User paczki, gdy skonfigurowanej klasy nie ma (stockowe `App\Models\User`
     * usunięte z projektu); własny model projektu (`AUTH_MODEL`) zostaje.
     */
    private function useUserModel(Repository $config): void
    {
        $configured = $config->get(self::USERS_MODEL);

        if (! is_string($configured) || ! class_exists($configured)) {
            $config->set(self::USERS_MODEL, User::class);
        }
    }

    private static function appKey(Repository $config): string
    {
        $key = $config->get('app.key');

        return is_string($key) && $key !== '' ? $key : throw new RuntimeException('APP_KEY is required for the e-mail index of zone accounts.');
    }
}
