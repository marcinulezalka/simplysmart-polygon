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

namespace Simplysmart\Polygon\Contracts;

use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Strona startowa konta strefy po zalogowaniu (klucz `home` configu strefy, docs/strefy.md), np. pulpit roli.
 * Zones\ZoneRedirects woła ją w zespole spatie bieżącej organizacji konta, więc role i uprawnienia liczą się tak jak
 * w żądaniach strefy.
 */
interface ZoneHome
{
    /**
     * Trasa strefy bez prefiksu (np. `registration.dashboard`); null albo nieistniejąca trasa — pulpit strefy
     * (Zones\ZoneRedirects::HOME_ROUTE).
     */
    public function routeFor(ZoneAccount $account): ?string;
}
