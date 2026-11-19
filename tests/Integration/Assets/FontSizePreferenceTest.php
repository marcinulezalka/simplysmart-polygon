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

namespace Simplysmart\Polygon\Tests\Integration\Assets;

use Illuminate\Support\Facades\Route;
use Simplysmart\Polygon\Enums\FontSize;
use Simplysmart\Polygon\View\FontSizePreference;
use Tests\TestCase;

/**
 * Rozmiar czcionki z ciasteczka `polygon_font_size` (View\FontSizePreference): wartość zapisana przez skrypt strefy
 * (bez szyfrowania) przechodzi przez EncryptCookies, nieznana wartość albo brak ciasteczka to rozmiar standardowy.
 */
class FontSizePreferenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->get('/_font-size', static fn(FontSizePreference $fontSize): string => $fontSize->current()->value);
    }

    public function test_a_plain_cookie_selects_the_font_size(): void
    {
        foreach (FontSize::cases() as $fontSize) {
            $this->withUnencryptedCookie(FontSizePreference::COOKIE, $fontSize->value)->get('/_font-size')->assertOk()->assertSeeText($fontSize->value);
        }
    }

    public function test_an_unknown_or_missing_cookie_means_standard(): void
    {
        $this->withUnencryptedCookie(FontSizePreference::COOKIE, 'huge')->get('/_font-size')->assertSeeText(FontSize::A->value);
        $this->get('/_font-size')->assertSeeText(FontSize::A->value);
    }

    public function test_every_font_size_has_a_label(): void
    {
        foreach (FontSize::cases() as $fontSize) {
            $this->assertStringNotContainsString('enums.', $fontSize->label());
        }
    }
}
