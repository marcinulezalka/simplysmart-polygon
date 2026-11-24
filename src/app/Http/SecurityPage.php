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

namespace Simplysmart\Polygon\Http;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Miejsce sekcji bezpieczeństwa konta (AccountRoutes::SECTIONS) w strefie: własna strona strefy z `auth.security_pages`
 * (Auth\ZoneAuthSettings), a bez niej kotwica sekcji na stronie bezpieczeństwa (AccountRoutes::SECURITY). Tu wracają
 * akcje sekcji i tu prowadzą odesłania do nich.
 */
final readonly class SecurityPage
{
    public function __construct(
        private UrlGenerator $url,
        private Redirector $redirect,
    ) {}

    public function url(ZoneDefinition $zone, string $section): string
    {
        $url = $this->url->route($this->routeName($zone, $section));

        return isset($zone->auth()->securityPages[$section]) ? $url : $url . '#' . $section;
    }

    /**
     * Pełna nazwa trasy strony, na której jest sekcja.
     */
    public function routeName(ZoneDefinition $zone, string $section): string
    {
        return $zone->routeName($zone->auth()->securityPages[$section] ?? AccountRoutes::SECURITY);
    }

    public function redirect(ZoneDefinition $zone, string $section): RedirectResponse
    {
        return $this->redirect->to($this->url($zone, $section));
    }
}
