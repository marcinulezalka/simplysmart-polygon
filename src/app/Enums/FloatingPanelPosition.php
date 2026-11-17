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
 * Położenie pływającego panelu akcji na ekranie (FloatingPanel\FloatingPanelPreference, docs/strefy.md → „Pływający
 * panel akcji”): rogi, środki boków i środki góry i dołu; domyślnie prawy dolny róg. Toast stoi przy panelu — przy
 * położeniu z boku obok niego (od strony środka ekranu) i chowa się pod panel w stronę krawędzi, przy położeniu na
 * środku góry albo dołu nad panelem (dół) albo pod nim (góra) i chowa się w stronę krawędzi (resources/scss/floating-panel.scss).
 * Teksty: `enums.floating_panel_position.<value>`.
 */
enum FloatingPanelPosition: string
{
    case TopLeft = 'top-left';
    case TopCenter = 'top-center';
    case TopRight = 'top-right';
    case MiddleLeft = 'middle-left';
    case MiddleRight = 'middle-right';
    case BottomLeft = 'bottom-left';
    case BottomCenter = 'bottom-center';
    case BottomRight = 'bottom-right';

    public const self DEFAULT = self::BottomRight;

    public function label(): string
    {
        return PackageText::get('enums.floating_panel_position.' . $this->value);
    }

    /**
     * Położenia w siatce 3 × 3 ekranu (wiersz po wierszu, null — środek ekranu, bez panelu) — do wyboru w ustawieniach.
     *
     * @return list<self|null>
     */
    public static function grid(): array
    {
        return [self::TopLeft, self::TopCenter, self::TopRight, self::MiddleLeft, null, self::MiddleRight, self::BottomLeft, self::BottomCenter, self::BottomRight];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
