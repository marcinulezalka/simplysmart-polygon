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

namespace Simplysmart\Polygon\Localization;

use DateTimeZone;
use Illuminate\Contracts\Config\Repository;
use Simplysmart\Polygon\PolygonConfig;

/**
 * Strefa czasowa aplikacji z `APP_TIMEZONE` (PolygonConfig::TIMEZONE). Stockowy config/app.php Laravela ma na sztywno
 * `'timezone' => 'UTC'`, więc bez tego `now()` i `today()` liczą czas UTC, a godziny grafików i wizyt są czasem
 * lokalnym — np. FreeSlots pokazywałby jako wolne sloty, które już minęły. Ustawia `app.timezone` i domyślną strefę PHP
 * (Carbon); pusta albo nieznana wartość zostawia strefę frameworka.
 */
final readonly class ApplicationTimezone
{
    public function __construct(private Repository $config) {}

    /**
     * Ustawia strefę z configu paczki i zwraca ją; null — strefa frameworka bez zmian.
     */
    public function apply(): ?string
    {
        $timezone = $this->config->get(PolygonConfig::TIMEZONE);

        if (! is_string($timezone) || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            return null;
        }

        $this->config->set('app.timezone', $timezone);
        date_default_timezone_set($timezone);

        return $timezone;
    }
}
