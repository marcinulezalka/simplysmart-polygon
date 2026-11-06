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

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Http\Middleware\RequireZonePermission;
use Simplysmart\Polygon\Zones\InMemoryZoneRegistry;
use Simplysmart\Polygon\Zones\RequestAccount;
use Simplysmart\Polygon\Zones\RequestZone;
use Simplysmart\Polygon\Zones\ZoneRedirects;
use Simplysmart\Polygon\Zones\ZoneSessionConfigurator;
use Simplysmart\Polygon\Zones\ZoneSessionManager;

/**
 * Mechanizm stref: rejestr (singleton), strefa i konto żądania, osobny magazyn sesji każdej strefy,
 * przekierowania auth/guest według strefy oraz alias middleware uprawnień `zone.permission` (docs/strefy.md). Widoki dostarcza paczka strefy.
 */
final class ZonesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ZoneRegistry::class, InMemoryZoneRegistry::class);
        $this->app->scoped(CurrentZone::class, RequestZone::class);
        $this->app->bind(CurrentAccount::class, RequestAccount::class);
        $this->app->extend('session', fn(): ZoneSessionManager => new ZoneSessionManager($this->app));
        $this->app->singleton(ZoneSessionConfigurator::class, fn(Application $app): ZoneSessionConfigurator => new ZoneSessionConfigurator(
            $app,
            $app->make(Repository::class),
            $this->zoneSessionManager($app),
            (array) $app->make(Repository::class)->get('session', []),
            ! $app->environment('local'),
        ));
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware(RequireZonePermission::ALIAS, RequireZonePermission::class);
        $this->useZoneRedirects();
        $this->app->afterResolving(HttpKernel::class, $this->useZoneRedirects(...));
    }

    /**
     * Przekierowania auth/guest według bieżącej strefy. Ustawiane także po rozwiązaniu kernela HTTP,
     * bo ApplicationBuilder::withMiddleware() ustawia wtedy własne (globalną trasę `login`).
     */
    private function useZoneRedirects(): void
    {
        Authenticate::redirectUsing(fn(): string => $this->app->make(ZoneRedirects::class)->guestRedirect());
        RedirectIfAuthenticated::redirectUsing(fn(): string => $this->app->make(ZoneRedirects::class)->userRedirect());
    }

    /**
     * Menedżer sesji aplikacji (wiązanie `session`, zastąpione przez ZoneSessionManager).
     */
    private function zoneSessionManager(Application $app): ZoneSessionManager
    {
        $manager = $app->make('session');

        return $manager instanceof ZoneSessionManager ? $manager : throw new LogicException('The session manager must be ' . ZoneSessionManager::class . '.');
    }
}
