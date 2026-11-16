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
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Simplysmart\Polygon\Zones\ZoneUrlKey;
use Symfony\Component\HttpFoundation\Response;

/**
 * Domyślny parametr hasha sesji (Zones\ZoneUrlKey) dla adresów strefy z `url_key`: route() tras po zalogowaniu
 * dokłada go sam. W grupie middleware strefy po StartSession.
 */
final readonly class ApplyZoneUrlKey
{
    public function __construct(
        private ZoneUrlKey $keys,
        private UrlGenerator $urls,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && method_exists($this->urls, 'defaults')) {
            $this->urls->defaults([ZoneUrlKey::PARAMETER => $this->keys->current($request->session())]);
        }

        return $next($request);
    }
}
