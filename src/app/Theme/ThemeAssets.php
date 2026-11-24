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

namespace Simplysmart\Polygon\Theme;

/**
 * Wejścia Vite motywu Polygon (`@packageAssets('polygon', ThemeAssets::ENTRIES)`, docs/assety.md → „Motyw Polygon”). Motyw
 * jest opcjonalny: używa go dokumentacja, a strefa z własnym motywem go nie ładuje.
 */
final class ThemeAssets
{
    /** @var list<string> Tokeny: jasny i ciemny, WCAG, WCAG duży kontrast. */
    public const array THEMES = [
        'resources/scss/polygon/theme.scss',
        'resources/scss/polygon/wcag/theme.scss',
        'resources/scss/polygon/wcag-contrast/theme.scss',
    ];

    /** @var list<string> Komponenty i tokeny. */
    public const array ENTRIES = [
        'resources/scss/polygon/components.scss',
        ...self::THEMES,
    ];
}
