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

namespace Simplysmart\Polygon\Pinpad;

/**
 * Układ klawiatury numerycznej pola PIN-u (resources/scss/pinpad.scss — siatka klawiszy, ukrywanie przycisku formularza; bez
 * palety) budowany Vite paczki jak Notifications\NotificationAssets; strefa wstawia go w `<head>`
 * (`@packageAssets('polygon', PinpadAssets::ENTRIES)`) i maluje klasy `.pg-pinpad*` swoimi tokenami. Klawisze daje komponent
 * `<x-polygon::pinpad>`, zachowanie — resources/js/pinpad.js (`initPinpad()` z wejścia JS strefy).
 */
final class PinpadAssets
{
    /** @var list<string> Wejścia Vite paczki w kolejności ładowania. */
    public const array ENTRIES = [
        'resources/scss/pinpad.scss',
    ];
}
