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

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Simplysmart\Polygon\Contracts\BlindIndex;
use Simplysmart\Polygon\Models\User;
use Simplysmart\Polygon\Security\HmacBlindIndex;
use Simplysmart\Polygon\Zones\PolygonZone;

/**
 * Konta: guard i provider strefy `polygon` (konta w `polygon_accounts`) dopisane do config/auth, model providera
 * `users` (konta frontendu, Models\User, gdy projekt nie ma własnego) i indeks e-maili kont (BlindIndex, HMAC z APP_KEY).
 */
final class AccountsServiceProvider extends ServiceProvider
{
    private const string USERS_MODEL = 'auth.providers.users.model';

    public function register(): void
    {
        $this->app->singleton(BlindIndex::class, static fn(Application $app): BlindIndex => new HmacBlindIndex(self::appKey($app->make(Repository::class))));
        $this->app->booting(function (): void {
            $config = $this->app->make(Repository::class);
            $config->set(PolygonZone::authConfig());
            $this->useUserModel($config);
        });
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
