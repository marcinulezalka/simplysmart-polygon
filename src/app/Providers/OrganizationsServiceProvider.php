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

namespace Simplysmart\Polygon\Providers;

use Illuminate\Support\ServiceProvider;
use Simplysmart\Polygon\Contracts\CurrentOrganization;
use Simplysmart\Polygon\Organizations\SessionCurrentOrganization;

/**
 * Organizacje: kontekst organizacji konta zalogowanego w strefie (Contracts\CurrentOrganization, sesja strefy żądania;
 * bez singletona, bo zależy od żądania).
 */
final class OrganizationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CurrentOrganization::class, SessionCurrentOrganization::class);
    }
}
