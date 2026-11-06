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

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Router;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Przekierowania middleware auth/guest liczone z bieżącej strefy (nie ma globalnej trasy `login`) i pulpit strefy
 * po zalogowaniu.
 */
final readonly class ZoneRedirects
{
    public const string LOGIN_ROUTE = 'auth.login';

    public const string HOME_ROUTE = 'dashboard';

    public function __construct(
        private CurrentZone $zone,
        private Router $router,
        private UrlGenerator $url,
    ) {}

    /**
     * Pulpit strefy (trasa `<prefiks>.dashboard`) — cel po zalogowaniu.
     */
    public function homeFor(ZoneDefinition $zone): string
    {
        return $this->url->route($zone->routeName(self::HOME_ROUTE));
    }

    /**
     * Logowanie strefy dla niezalogowanego; bez niego 404 (nie zdradza istnienia trasy).
     *
     * @throws NotFoundHttpException
     */
    public function guestRedirect(): string
    {
        $route = $this->route(self::LOGIN_ROUTE) ?? throw new NotFoundHttpException();

        return $this->url->route($route);
    }

    /**
     * Pulpit strefy dla zalogowanego, który wszedł na trasę tylko dla gości.
     */
    public function userRedirect(): string
    {
        $route = $this->route(self::HOME_ROUTE);

        return $route !== null ? $this->url->route($route) : $this->url->to('/');
    }

    private function route(string $name): ?string
    {
        if (! $this->zone->has()) {
            return null;
        }

        $route = $this->zone->get()->routeName($name);

        return $this->router->has($route) ? $route : null;
    }
}
