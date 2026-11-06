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

namespace Simplysmart\Polygon;

use Illuminate\Support\ServiceProvider;

/**
 * Provider paczki Polygon, rejestrowany przez auto-discovery Laravela.
 *
 * Paczka jest instalowana tylko jako zależność deweloperska (`require-dev`), więc na serwerach
 * (`composer install --no-dev`) ten provider nie istnieje i nie może być wymagany przez kod aplikacji.
 */
final class PolygonServiceProvider extends ServiceProvider {}
