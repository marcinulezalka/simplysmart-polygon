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

namespace Simplysmart\Polygon\Permissions;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Simplysmart\Polygon\Http\Middleware\RequireZonePermission;
use Simplysmart\Polygon\Models\ZoneAccount;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Uprawnienia wymagane przez trasy strefy (Http\Middleware\RequireZonePermission): czy konto ma któreś z wymaganych
 * i czy w swoim bieżącym kontekście (ContextPermissions) może otworzyć adres — np. stronę zapamiętaną przed logowaniem.
 */
final readonly class RoutePermissions
{
    public function __construct(
        private Router $router,
        private ContextPermissions $context,
    ) {}

    /**
     * Czy konto ma dowolne z uprawnień w guardzie (w zespole i roli ustawionych dla żądania).
     *
     * @param array<array-key, string> $permissions
     */
    public static function grants(ZoneAccount $account, string $guard, array $permissions): bool
    {
        return array_any($permissions, static fn(string $permission): bool => $account->checkPermissionTo($permission, $guard));
    }

    /**
     * Czy konto ma w swoim bieżącym kontekście uprawnienia trasy pod adresem (GET); adres bez trasy nie jest dostępny.
     */
    public function canOpen(ZoneAccount $account, string $guard, string $url): bool
    {
        try {
            $route = $this->router->getRoutes()->match(Request::create($url));
        } catch (HttpException) {
            return false;
        }

        $required = [];

        foreach ($this->router->gatherRouteMiddleware($route) as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, RequireZonePermission::class . ':')) {
                $required[] = explode(',', substr($middleware, strlen(RequireZonePermission::class) + 1));
            }
        }

        return $required === [] || $this->context->within($account, static fn(): bool => array_all($required, static fn(array $permissions): bool => self::grants($account, $guard, $permissions)));
    }
}
