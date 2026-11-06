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

namespace Simplysmart\Polygon\Zones;

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Simplysmart\Polygon\Http\Middleware\PreventRequestForgeryWithoutCookie;
use Simplysmart\Polygon\Http\Middleware\RecordHistoryActor;
use Simplysmart\Polygon\Http\Middleware\UseZone;

/**
 * Rejestruje grupę middleware strefy (`zone.<nazwa>`), używaną zamiast grupy `web`.
 *
 * UseZone musi poprzedzać StartSession (ustawia sesję i guard strefy); RecordHistoryActor potrzebuje sesji (konto
 * z guarda strefy), więc stoi po StartSession.
 */
final readonly class ZoneMiddlewareRegistrar
{
    private const string GROUP_PREFIX = 'zone.';

    public function __construct(
        private Router $router,
    ) {}

    /**
     * Nazwa grupy middleware strefy.
     */
    public static function group(ZoneDefinition $zone): string
    {
        return self::GROUP_PREFIX . $zone->name;
    }

    public function register(ZoneDefinition $zone): void
    {
        $this->router->middlewareGroup(self::group($zone), [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            UseZone::class . ':' . $zone->name,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgeryWithoutCookie::class,
            RecordHistoryActor::class,
            SubstituteBindings::class,
        ]);
    }
}
