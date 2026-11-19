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
 * Rozmiar czcionki interfejsu (atrybut `data-font-size` na `<html>`, View\FontSizePreference): standardowy (A),
 * powiększony (AA) i znacznie powiększony (AAA). Każda strefa skaluje swój root font-size w `theme.scss` przy
 * `:root[data-font-size='aa']` i `:root[data-font-size='aaa']`, tak jak tryb kolorystyczny (Enums\ColorTheme).
 */
enum FontSize: string
{
    case A = 'a';
    case Aa = 'aa';
    case Aaa = 'aaa';

    public function label(): string
    {
        return PackageText::get('enums.font_size.' . $this->value);
    }
}
