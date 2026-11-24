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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Simplysmart\Polygon\Auth\MissingPin;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Zalogowane konto bez PIN-u (Auth\MissingPin): każda strona dla zalogowanych prowadzi na definiowanie PIN-u, poza samą tą
 * stroną, zapisem PIN-u i wylogowaniem; żądanie JSON dostaje 403.
 */
final readonly class EnsurePinDefined
{
    public function __construct(
        private MissingPin $missing,
        private CurrentAccount $account,
        private CurrentZone $zone,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $zone = $this->zone->get();

        if (! $this->missing->of($zone, $this->account->get()) || in_array($request->route()?->getName(), $this->missing->allowedRoutes($zone), true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            throw new AccessDeniedHttpException();
        }

        return new RedirectResponse($this->missing->url($zone));
    }
}
