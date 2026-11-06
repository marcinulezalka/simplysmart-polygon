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

/*
 * Ustawienia paczki Polygon, łączone przez PolygonServiceProvider (mergeConfigFrom). Nie publikuj tego pliku do
 * projektu — zmieniaj wartości przez `.env`.
 */

$envString = static fn(string $key, string $default): string => is_scalar($value = env($key, $default)) ? (string) $value : $default;

return [

    'locale' => [

        /*
         * Waluta aplikacji (kod ISO 4217): włączona i domyślna w słowniku walut od pierwszego seedowania.
         */
        'currency' => $envString('APP_CURRENCY', 'PLN'),

        /*
         * Włączone języki interfejsu (kody rozdzielone przecinkami): tłumaczenia nazw słowników w `json_i18n`.
         */
        'enabled' => array_values(array_filter(array_map(trim(...), explode(',', $envString('APP_LOCALES', 'pl'))))),

    ],

    'localization' => [

        /*
         * Kraj aplikacji (ISO 3166-1 alpha-2), gdy słownik krajów nie ma jeszcze kraju domyślnego (is_default).
         */
        'country' => $envString('APP_COUNTRY', 'PL'),

        /*
         * Stawka VAT (procent), gdy kraj domyślny nie ma w słowniku stawki podstawowej na dany dzień.
         */
        'vat_percent' => $envString('APP_DEFAULT_VAT', '23'),

    ],

    'install' => [

        /*
         * Pliki instalacji projektu (JSON: strefy, uprawnienia, role, konta startowe; docs/instalacja.md), stosowane po
         * pliku paczki `database/install/polygon.json` — ścieżki rozdzielone przecinkami, względem katalogu projektu.
         */
        'files' => array_values(array_filter(array_map(trim(...), explode(',', $envString('POLYGON_INSTALL_FILES', ''))))),

    ],

];
