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

namespace Simplysmart\Polygon\Tests\Theme;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simplysmart\Polygon\Enums\ColorTheme;
use Simplysmart\Polygon\Theme\ThemeTokens;

/**
 * Motyw Polygon: komplet tokenów w każdym trybie i kontrast trybów WCAG (docs/assety.md → „Tryby kolorystyczne”).
 */
final class ThemeTokensTest extends TestCase
{
    /** Kontrast obramowań kontrolek (WCAG 1.4.11). */
    private const float CONTROL_RATIO = 3.0;

    /** Tekst => tła, na których stoi w komponentach motywu i layoucie dokumentacji. */
    private const array TEXT_PAIRS = [
        '--pg-text' => ['--pg-bg', '--pg-surface', '--pg-sunken', '--pg-accent-bg', '--pg-success-bg', '--pg-warning-bg', '--pg-danger-bg'],
        '--pg-muted' => ['--pg-bg', '--pg-surface', '--pg-sunken'],
        '--pg-accent' => ['--pg-bg', '--pg-surface', '--pg-accent-bg'],
        '--pg-success' => ['--pg-success-bg'],
        '--pg-warning' => ['--pg-warning-bg'],
        '--pg-danger' => ['--pg-surface', '--pg-danger-bg'],
        '--pg-on-accent' => ['--pg-accent', '--pg-accent-hover', '--pg-danger'],
    ];

    /**
     * @return array<string, array{ColorTheme, float}>
     */
    public static function wcagModes(): array
    {
        return ['wcag' => [ColorTheme::Wcag, 4.5], 'wcag-contrast' => [ColorTheme::WcagContrast, 7.0]];
    }

    #[Test]
    public function every_mode_defines_the_same_tokens(): void
    {
        $tokens = new ThemeTokens();

        $this->assertSame([ColorTheme::Light, ColorTheme::Dark, ColorTheme::Wcag, ColorTheme::WcagContrast], $tokens->modes());
        $this->assertArrayHasKey('--pg-radius', $tokens->base());

        foreach ($tokens->modes() as $mode) {
            $this->assertSame(array_keys($tokens->of(ColorTheme::Light)), array_keys($tokens->of($mode)), $mode->value);
        }
    }

    #[Test]
    #[DataProvider('wcagModes')]
    public function wcag_modes_keep_their_contrast(ColorTheme $mode, float $minimum): void
    {
        $colors = (new ThemeTokens())->of($mode);
        $failed = [];

        foreach (self::TEXT_PAIRS as $text => $backgrounds) {
            foreach ($backgrounds as $background) {
                $ratio = $this->contrast($colors[$text], $colors[$background]);

                if ($ratio < $minimum) {
                    $failed[] = sprintf('%s / %s: %.2f', $text, $background, $ratio);
                }
            }
        }

        $this->assertSame([], $failed);
        $this->assertGreaterThanOrEqual(self::CONTROL_RATIO, $this->contrast($colors['--pg-control'], $colors['--pg-surface']));
    }

    private function contrast(string $first, string $second): float
    {
        $levels = [$this->luminance($first), $this->luminance($second)];

        return (max($levels) + 0.05) / (min($levels) + 0.05);
    }

    private function luminance(string $color): float
    {
        $this->assertMatchesRegularExpression('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $color);
        $hex = substr($color, 1);
        $digits = strlen($hex) === 3 ? (string) preg_replace('/./', '$0$0', $hex) : $hex;
        $channels = array_map(static function (string $channel): float {
            $value = hexdec($channel) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split($digits, 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
