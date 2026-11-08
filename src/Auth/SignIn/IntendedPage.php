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

namespace Simplysmart\Polygon\Auth\SignIn;

use Illuminate\Contracts\Session\Session;
use Simplysmart\Polygon\Zones\ZoneDefinition;
use Simplysmart\Polygon\Zones\ZoneRedirects;
use Simplysmart\Polygon\Zones\ZoneUrlKey;

/**
 * Zamierzona strona sprzed logowania (`url.intended` Laravela, Redirector::intended()) przygotowana do powrotu po
 * zalogowaniu w strefie: w strefie z `url_key` dostaje hash bieżącej sesji (Zones\ZoneUrlKey::rewrite() — zapamiętana
 * z hashem poprzedniej sesji dawałaby 404), a ogólny pulpit strefy (Zones\ZoneRedirects::isDashboard()) jest pomijany,
 * żeby nie zastąpił strony startowej konta. forget() pomija ją całkiem (pierwsze logowanie).
 */
final readonly class IntendedPage
{
    /** Klucz sesji Laravela z zamierzoną stroną. */
    public const string SESSION_KEY = 'url.intended';

    public function __construct(
        private ZoneUrlKey $urlKeys,
        private ZoneRedirects $redirects,
    ) {}

    public function prepare(ZoneDefinition $zone, Session $session): void
    {
        $intended = $session->get(self::SESSION_KEY);

        if (! is_string($intended)) {
            return;
        }

        $intended = $zone->urlKey ? $this->urlKeys->rewrite($intended, $zone->slug, $session) : $intended;

        if ($this->redirects->isDashboard($intended)) {
            $this->forget($session);

            return;
        }

        $session->put(self::SESSION_KEY, $intended);
    }

    public function forget(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }
}
