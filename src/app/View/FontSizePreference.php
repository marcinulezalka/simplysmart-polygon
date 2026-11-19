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
use Simplysmart\Polygon\Enums\FontSize;

/**
 * Rozmiar czcionki wybrany w przeglądarce: ciasteczko `polygon_font_size` (wspólne dla stref, bez szyfrowania —
 * zapisuje je skrypt przełącznika strefy, więc Providers\AssetsServiceProvider wyłącza je z EncryptCookies);
 * nieznana wartość albo brak ciasteczka to FontSize::A. Widok strefy wstawia current() w `data-font-size` na `<html>`.
 */
final readonly class FontSizePreference
{
    /** Nazwa ciasteczka z rozmiarem. */
    public const string COOKIE = 'polygon_font_size';

    public function __construct(
        private Request $request,
    ) {}

    public function current(): FontSize
    {
        $value = $this->request->cookie(self::COOKIE);

        return is_string($value) ? FontSize::tryFrom($value) ?? FontSize::A : FontSize::A;
    }
}
