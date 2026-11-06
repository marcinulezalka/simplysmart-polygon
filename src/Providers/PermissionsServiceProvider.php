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
use Simplysmart\Polygon\Permissions\PermissionDefaults;

/**
 * Uprawnienia: konfiguracja spatie/laravel-permission (PermissionDefaults: modele paczki, nazwy tabel, bez zespołów)
 * ustawiana po rejestracji wszystkich providerów, gdy config spatie jest już scalony.
 */
final class PermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->booting(function (): void {
            $this->app->make(PermissionDefaults::class)->apply();
        });
    }
}
