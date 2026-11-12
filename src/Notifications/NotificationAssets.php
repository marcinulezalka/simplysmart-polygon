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

namespace Simplysmart\Polygon\Notifications;

/**
 * Układ toastu i dzwonka (resources/scss/notifications.scss) budowany własnym Vite paczki (`vite.config.js`,
 * orkiestrator `bin/assets.mjs`) do `public/build/` i rejestrowany w Assets\PackageAssets pod nazwą paczki `polygon`
 * (Providers\AssetsServiceProvider) — strefa wstawia go w `<head>` obok swoich assetów
 * (`@packageAssets('polygon', NotificationAssets::ENTRIES)`) i maluje te same klasy `.pg-*` swoimi tokenami.
 * Zachowanie (resources/js/notifications.js) nie jest wejściem Vite tej paczki — to czyste źródło ES, bez efektów
 * przy wczytaniu; strefa importuje z niego `initNotifications()` we własnym wejściu JS i tam dostaje bundel.
 */
final class NotificationAssets
{
    /** Katalog `public/` paczki z buildem. */
    public const string PUBLIC_PATH = __DIR__ . '/../../public';

    /** @var list<string> Wejścia Vite paczki w kolejności ładowania. */
    public const array ENTRIES = [
        'resources/scss/notifications.scss',
    ];
}
