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

namespace Simplysmart\Polygon\FloatingPanel;

/**
 * Układ pływającego panelu akcji (resources/scss/floating-panel.scss — położenia, toast przy panelu i kierunki jego
 * wejścia i wyjścia, przezroczystość w spoczynku; bez palety) budowany Vite paczki jak Notifications\NotificationAssets;
 * strefa wstawia go w `<head>` (`@packageAssets('polygon', FloatingPanelAssets::ENTRIES)`) i maluje klasy `.pg-float*`
 * swoimi tokenami. Zachowanie — resources/js/floating-panel.js (`initFloatingPanel()` z wejścia JS strefy).
 */
final class FloatingPanelAssets
{
    /** @var list<string> Wejścia Vite paczki w kolejności ładowania. */
    public const array ENTRIES = [
        'resources/scss/floating-panel.scss',
    ];
}
