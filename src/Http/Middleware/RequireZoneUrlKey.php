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
use Illuminate\Routing\Route;
use Simplysmart\Polygon\Zones\ZoneUrlKey;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Trasy strefy pod hashem sesji (`/<hash>/<slug>/…`): hash spoza bieżącej sesji to 404 (zanim zadziała logowanie),
 * a poprawny zostaje usunięty z parametrów trasy, więc kontrolery go nie widzą.
 */
final readonly class RequireZoneUrlKey
{
    public function __construct(
        private ZoneUrlKey $keys,
    ) {}

    /**
     * @param Closure(Request): Response $next
     *
     * @throws NotFoundHttpException Gdy hash nie jest hashem bieżącej sesji.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if (! $route instanceof Route || ! $request->hasSession() || ! $this->keys->matches($request->session(), $route->parameter(ZoneUrlKey::PARAMETER))) {
            throw new NotFoundHttpException();
        }

        $route->forgetParameter(ZoneUrlKey::PARAMETER);

        return $next($request);
    }
}
