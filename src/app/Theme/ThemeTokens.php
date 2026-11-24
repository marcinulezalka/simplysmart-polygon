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

use Simplysmart\Polygon\Enums\ColorTheme;
use Simplysmart\Polygon\Providers\PolygonServiceProvider;

/**
 * Tokeny motywu Polygon czytane wprost z arkuszy (ThemeAssets::THEMES), więc nowy token pojawia się w Preview bez zmian strony:
 * mixin `base` — wspólne, mixin o nazwie trybu (`light`, `dark`, `wcag`, `wcag-contrast`) — wartości trybu.
 */
final readonly class ThemeTokens
{
    /** Mixin tokenów wspólnych dla trybów. */
    private const string BASE = 'base';

    private const string MIXIN_PATTERN = '/@mixin\s+([a-z-]+)\s*\{([^{}]*)\}/';

    private const string TOKEN_PATTERN = '/(--pg-[a-z-]+):\s*([^;]+);/';

    /**
     * @var array<string, array<string, string>> Mixin => (token => wartość).
     */
    private array $mixins;

    public function __construct()
    {
        $mixins = [];

        foreach (ThemeAssets::THEMES as $sheet) {
            preg_match_all(self::MIXIN_PATTERN, (string) file_get_contents(PolygonServiceProvider::ROOT . '/' . $sheet), $blocks, PREG_SET_ORDER);

            foreach ($blocks as $block) {
                preg_match_all(self::TOKEN_PATTERN, $block[2], $tokens, PREG_SET_ORDER);
                $mixins[$block[1]] = array_column($tokens, 2, 1);
            }
        }

        $this->mixins = $mixins;
    }

    /**
     * Tryby z własnymi wartościami (bez `auto`, który wybiera jeden z nich).
     *
     * @return list<ColorTheme>
     */
    public function modes(): array
    {
        return array_values(array_filter(ColorTheme::cases(), fn(ColorTheme $mode): bool => isset($this->mixins[$mode->value])));
    }

    /**
     * Tokeny wspólne (zaokrąglenia, kroje).
     *
     * @return array<string, string>
     */
    public function base(): array
    {
        return $this->mixins[self::BASE] ?? [];
    }

    /**
     * Tokeny trybu (kolory, cienie).
     *
     * @return array<string, string>
     */
    public function of(ColorTheme $mode): array
    {
        return $this->mixins[$mode->value] ?? [];
    }
}
