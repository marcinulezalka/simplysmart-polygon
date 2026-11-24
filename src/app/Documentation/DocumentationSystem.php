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

use Simplysmart\Polygon\Contracts\HasLabel;
use Simplysmart\Polygon\Localization\PackageText;

/**
 * System dokumentacji (docs/dokumentacja.md): wartość to katalog w paczce i segment adresu. Wiki — dokumentacja
 * biznesowa użytkownika, Docs — techniczna programisty, Preview — design system. Wszystkie po zalogowaniu w strefie.
 */
enum DocumentationSystem: string implements HasLabel
{
    case Wiki = 'wiki';
    case Docs = 'docs';
    case Preview = 'preview';

    public function label(): string
    {
        return PackageText::get('documentation.systems.' . $this->value);
    }
}
