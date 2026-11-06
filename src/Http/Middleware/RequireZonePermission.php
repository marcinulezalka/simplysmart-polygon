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

namespace Simplysmart\Polygon\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Models\ZoneAccount;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Trasa wymagająca uprawnienia (`zone.permission:<uprawnienie>`): konto z guarda bieżącej strefy z uprawnieniem spatie
 * w tym guardzie (bezpośrednio albo przez rolę). Brak uprawnienia, także nieznanego, to 403. Pliki tras strefy nie znają
 * guarda, więc używają Zones\ZonePermission::middleware().
 */
final readonly class RequireZonePermission
{
    public const string ALIAS = 'zone.permission';

    public function __construct(
        private CurrentZone $zone,
        private CurrentAccount $account,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $account = $this->account->get();

        if (! $account instanceof ZoneAccount || ! $account->checkPermissionTo($permission, $this->zone->get()->guard)) {
            throw new AccessDeniedHttpException();
        }

        return $next($request);
    }
}
