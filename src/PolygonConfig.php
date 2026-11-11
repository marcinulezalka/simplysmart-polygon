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

    /** Godzina nocnej synchronizacji z systemem fakturowym (`HH:MM`, `INVOICING_SYNC_AT`). */
    public const string INVOICING_SYNC_AT = self::KEY . '.invoicing.sync_at';

    /** Domyślna godzina nocnej synchronizacji z systemem fakturowym. */
    public const string INVOICING_SYNC_AT_DEFAULT = '02:30';

    /** Waluta aplikacji (ISO 4217, `APP_CURRENCY`). */
    public const string CURRENCY = self::KEY . '.locale.currency';

    /** Języki interfejsu (`APP_LOCALES`). */
    public const string LOCALES = self::KEY . '.locale.enabled';

    /** Kraj aplikacji (ISO 3166-1 alpha-2, `APP_COUNTRY`). */
    public const string COUNTRY = self::KEY . '.localization.country';

    /** Stawka VAT bez słownika (`APP_DEFAULT_VAT`). */
    public const string VAT_PERCENT = self::KEY . '.localization.vat_percent';

    /** Strefa czasowa aplikacji (IANA, `APP_TIMEZONE`; pusta — strefa frameworka). */
    public const string TIMEZONE = self::KEY . '.localization.timezone';

    /** Ustawienia logowania stref (domyślne; strefa nadpisuje kluczem `auth`). */
    public const string AUTH = self::KEY . '.auth';

    /** Czas życia sesji strefy z logowaniem w minutach (`ZONE_SESSION_LIFETIME`). */
    public const string SESSION_LIFETIME = self::KEY . '.zones.session_lifetime';

    /** Config strefy `polygon` (Providers\PolygonZoneServiceProvider). */
    public const string ZONE = self::KEY . '.zone';

    /** Zestawy ikon Iconify dozwolone do wyboru przez użytkownika (`POLYGON_ICON_SETS`; Icons\IconCatalog). */
    public const string ICON_SETS = self::KEY . '.icons.sets';

    /** Adres API Iconify (`POLYGON_ICONIFY_API`; Icons\IconCatalog). */
    public const string ICON_API = self::KEY . '.icons.api';

    /** Pliki instalacji projektu (`POLYGON_INSTALL_FILES`). */
    public const string INSTALL_FILES = self::KEY . '.install.files';
}
