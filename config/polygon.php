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
$envInt = static fn(string $key, int $default): int => is_numeric($value = env($key)) ? (int) $value : $default;

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

    'zones' => [

        /*
         * Czas życia sesji (w minutach) strefy z logowaniem, gdy strefa nie poda własnego (docs/strefy.md).
         */
        'session_lifetime' => $envInt('ZONE_SESSION_LIFETIME', 30),

    ],

    /*
     * Strefa `polygon` (konta w `polygon_accounts`, Providers\PolygonZoneServiceProvider): guard i provider kont,
     * bez ekranów logowania (polygon nie ma widoków; ekrany dostarcza paczka strefy w `views`).
     */
    'zone' => [
        'name' => 'polygon',
        'slug' => $envString('POLYGON_SLUG', 'polygon'),
        'model' => Simplysmart\Polygon\Models\PolygonAccount::class,
    ],

    'auth' => [

        /*
         * Logowanie stref z kontami: e-mail → kod z e-maila → PIN (przy pierwszym logowaniu ustawienie PIN-u).
         * Strefa nadpisuje dowolny klucz w swoim configu (`zone.auth`); listy `steps` i `pin.lockout_minutes`
         * zastępuje w całości.
         */
        'steps' => [
            Simplysmart\Polygon\Auth\SignIn\Steps\EmailCodeStep::class,
            Simplysmart\Polygon\Auth\SignIn\Steps\PinSetupStep::class,
            Simplysmart\Polygon\Auth\SignIn\Steps\PinStep::class,
        ],

        'code' => [
            'length' => 6,
            'ttl_minutes' => $envInt('AUTH_CODE_TTL', 10),
            'max_attempts' => 5,
            'resend_cooldown_seconds' => 60,
        ],

        'pin' => [
            /*
             * Dopuszczalna długość nowego PIN-u (cyfry), w granicach 4–16 (Auth\PinLength); wpisany istniejący PIN jest
             * sprawdzany w pełnym zakresie 4–16, więc zmiana nikogo nie blokuje.
             */
            'min_length' => $envInt('AUTH_PIN_MIN_LENGTH', Simplysmart\Polygon\Auth\PinLength::MIN),
            'max_length' => $envInt('AUTH_PIN_MAX_LENGTH', Simplysmart\Polygon\Auth\PinLength::MAX),
            'stage_ttl_minutes' => 5,
            'max_attempts' => 5,

            /*
             * Czas kolejnych blokad po błędnych PIN-ach (minuty); ostatni powtarza się. Licznik i poziom blokady
             * zeruje dopiero udane logowanie.
             */
            'lockout_minutes' => [15, 60, 1440],
        ],

        /*
         * Limity prób (max w decay_minutes), klucze cache z prefiksem strefy.
         */
        'limits' => [
            'code_requests' => ['max' => 5, 'decay_minutes' => 15],
            'account_code_requests' => ['max' => 10, 'decay_minutes' => 60],
            'code_checks' => ['max' => 10, 'decay_minutes' => 1],
            'account_code_checks' => ['max' => 20, 'decay_minutes' => 60],
            'pin_checks' => ['max' => 10, 'decay_minutes' => 1],
            'pin_changes' => ['max' => 5, 'decay_minutes' => 15],
        ],

        /*
         * Zaufane urządzenia: po kodzie z e-maila można zapamiętać przeglądarkę na `lifetime_days` dni; logowanie
         * pomija wtedy kod, ale zawsze wymaga PIN-u.
         */
        'trusted_devices' => [
            'enabled' => (bool) env('AUTH_TRUSTED_DEVICES', true),
            'lifetime_days' => $envInt('AUTH_TRUSTED_DEVICE_DAYS', 30),
            'max_per_account' => 10,
        ],

        'route_throttle_per_minute' => 20,

        'challenge_prune_hours' => 24,

    ],

    'install' => [

        /*
         * Pliki instalacji projektu (JSON: strefy, uprawnienia, role, konta startowe; docs/instalacja.md), stosowane po
         * pliku paczki `database/install/polygon.json` — ścieżki rozdzielone przecinkami, względem katalogu projektu.
         */
        'files' => array_values(array_filter(array_map(trim(...), explode(',', $envString('POLYGON_INSTALL_FILES', ''))))),

    ],

];
