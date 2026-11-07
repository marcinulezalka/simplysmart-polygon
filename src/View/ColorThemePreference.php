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

namespace Simplysmart\Polygon\View;

use Illuminate\Http\Request;
use Simplysmart\Polygon\Enums\ColorTheme;

/**
 * Tryb kolorystyczny wybrany w przeglądarce: ciasteczko `polygon_theme` (wspólne dla stref, bez szyfrowania — zapisuje
 * je skrypt przełącznika strefy, więc Providers\AssetsServiceProvider wyłącza je z EncryptCookies); nieznana wartość
 * albo brak ciasteczka to ColorTheme::Auto. Widok strefy wstawia current() w `data-theme` na `<html>`.
 */
final readonly class ColorThemePreference
{
    /** Nazwa ciasteczka z trybem. */
    public const string COOKIE = 'polygon_theme';

    public function __construct(
        private Request $request,
    ) {}

    public function current(): ColorTheme
    {
        $value = $this->request->cookie(self::COOKIE);

        return is_string($value) ? ColorTheme::tryFrom($value) ?? ColorTheme::Auto : ColorTheme::Auto;
    }
}
