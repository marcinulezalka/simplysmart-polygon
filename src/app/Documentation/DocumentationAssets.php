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

namespace Simplysmart\Polygon\Documentation;

use Simplysmart\Polygon\FloatingPanel\FloatingPanelAssets;
use Simplysmart\Polygon\Notifications\NotificationAssets;
use Simplysmart\Polygon\Theme\ThemeAssets;

/**
 * Wejścia Vite layoutu dokumentacji i przykładów Preview (`@packageAssets('polygon', …)`, vite.config.js paczki).
 */
final class DocumentationAssets
{
    /** @var list<string> Strona dokumentacji. */
    public const array ENTRIES = [
        'resources/scss/documentation/app.scss',
        ...ThemeAssets::THEMES,
        'resources/js/documentation.js',
    ];

    /** @var list<string> Przykład Preview polygonu (ramka): układ komponentów polygonu i motyw Polygon. */
    public const array EXAMPLE = [
        ...NotificationAssets::ENTRIES,
        ...FloatingPanelAssets::ENTRIES,
        'resources/scss/documentation/example.scss',
        ...ThemeAssets::ENTRIES,
        'resources/js/documentation-example.js',
    ];
}
