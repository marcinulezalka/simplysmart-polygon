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

namespace Simplysmart\Polygon\Enums;

use Simplysmart\Polygon\Localization\PackageText;

/**
 * Tryb kolorystyczny interfejsu (atrybut `data-theme` na `<html>`, View\ColorThemePreference): automatyczny według
 * systemu (`prefers-color-scheme`, a `prefers-contrast: more` — duży kontrast), jasny, ciemny, WCAG (kontrast co
 * najmniej 4.5:1, AA) i WCAG duży kontrast (co najmniej 7:1, AAA). Każda strefa ma własne arkusze motywów
 * (`theme.scss`, `wcag/theme.scss`, `wcag-contrast/theme.scss`).
 */
enum ColorTheme: string
{
    case Auto = 'auto';
    case Light = 'light';
    case Dark = 'dark';
    case Wcag = 'wcag';
    case WcagContrast = 'wcag-contrast';

    public function label(): string
    {
        return PackageText::get('enums.color_theme.' . $this->value);
    }
}
