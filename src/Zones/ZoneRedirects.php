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

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Router;
use Simplysmart\Polygon\Contracts\CurrentOrganization;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Contracts\ZoneHome;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Permissions\OrganizationRoles;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Przekierowania middleware auth/guest liczone z bieżącej strefy (nie ma globalnej trasy `login`) i strona startowa
 * po zalogowaniu: trasa z Contracts\ZoneHome strefy (`home`), a bez niej pulpit strefy.
 */
final readonly class ZoneRedirects
{
    public const string LOGIN_ROUTE = 'auth.login';

    public const string HOME_ROUTE = 'dashboard';

    public function __construct(
        private CurrentZone $zone,
        private Router $router,
        private UrlGenerator $url,
        private Container $container,
        private OrganizationRoles $roles,
        private CurrentOrganization $organization,
    ) {}

    /**
     * Cel po zalogowaniu: trasa, którą Contracts\ZoneHome strefy wybiera dla konta (liczona w zespole spatie bieżącej
     * organizacji konta; hero i konto bez organizacji — role globalne), a gdy strefa jej nie ma, nie wybiera żadnej albo
     * trasy nie ma — pulpit strefy (trasa `<prefiks>.dashboard`).
     */
    public function homeFor(ZoneDefinition $zone, ?ZoneAccount $account = null): string
    {
        $route = $account !== null && $zone->home !== null ? $this->accountHome($zone->home, $account) : null;
        $route = $route !== null && $this->router->has($zone->routeName($route)) ? $route : self::HOME_ROUTE;

        return $this->url->route($zone->routeName($route));
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

    /**
     * @param class-string<ZoneHome> $home
     */
    private function accountHome(string $home, ZoneAccount $account): ?string
    {
        $resolver = $this->container->make($home);

        return $this->roles->within(
            $account,
            $this->organization->isHero() ? null : $this->organization->id(),
            static fn(): ?string => $resolver->routeFor($account),
        );
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
