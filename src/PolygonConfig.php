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

/**
 * Klucze configu paczki (`config/polygon.php`, scalany pod KEY przez PolygonServiceProvider); kod czyta config tylko
 * przez te stałe, nie literały.
 */
final class PolygonConfig
{
    public const string KEY = 'polygon';

    /** Waluta aplikacji (ISO 4217, `APP_CURRENCY`). */
    public const string CURRENCY = self::KEY . '.locale.currency';

    /** Języki interfejsu (`APP_LOCALES`). */
    public const string LOCALES = self::KEY . '.locale.enabled';

    /** Kraj aplikacji (ISO 3166-1 alpha-2, `APP_COUNTRY`). */
    public const string COUNTRY = self::KEY . '.localization.country';

    /** Stawka VAT bez słownika (`APP_DEFAULT_VAT`). */
    public const string VAT_PERCENT = self::KEY . '.localization.vat_percent';

    /** Pliki instalacji projektu (`POLYGON_INSTALL_FILES`). */
    public const string INSTALL_FILES = self::KEY . '.install.files';
}
