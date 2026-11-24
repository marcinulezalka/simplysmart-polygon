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

namespace Simplysmart\Polygon\Auth;

use Simplysmart\Polygon\Contracts\HasPinCredential;
use Simplysmart\Polygon\Contracts\SignInAccount;
use Simplysmart\Polygon\Http\AccountRoutes;
use Simplysmart\Polygon\Http\SecurityPage;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Brakujący PIN zalogowanego konta w strefie, która definiuje PIN po zalogowaniu (ZoneAuthSettings::definesPinInAccount()):
 * konto bez PIN-u (pierwsze logowanie albo po resecie) ma dostęp tylko do strony sekcji `pin` (Http\SecurityPage), zapisu
 * PIN-u i wylogowania, dopóki go nie ustawi (Http\Middleware\EnsurePinDefined, DefinePin).
 */
final readonly class MissingPin
{
    public function __construct(
        private SecurityPage $page,
    ) {}

    public function of(ZoneDefinition $zone, ?SignInAccount $account): bool
    {
        return $zone->auth()->definesPinInAccount() && $account instanceof HasPinCredential && ! $account->hasPin();
    }

    /**
     * Adres definiowania PIN-u.
     */
    public function url(ZoneDefinition $zone): string
    {
        return $this->page->url($zone, AccountRoutes::SECTION_PIN);
    }

    /**
     * Pełne nazwy tras dostępnych bez PIN-u.
     *
     * @return list<string>
     */
    public function allowedRoutes(ZoneDefinition $zone): array
    {
        return [
            $this->page->routeName($zone, AccountRoutes::SECTION_PIN),
            $zone->routeName(AccountRoutes::PIN_DEFINE),
            $zone->routeName('auth.logout'),
        ];
    }
}
