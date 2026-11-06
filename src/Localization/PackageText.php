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

namespace Simplysmart\Polygon\Localization;

use Illuminate\Container\Container;
use Simplysmart\Polygon\Contracts\TextTranslator;

/**
 * Teksty paczki w przestrzeni `polygon::` (lang/<locale>/*.php, zarejestrowane przez PolygonServiceProvider) przez
 * kaskadę Contracts\TextTranslator: nadpisanie `lang/vendor/polygon/<locale>/` projektu → paczka → `lang/` projektu →
 * klucz, bez języka zapasowego. Dla kodu paczki bez dostępu do kontenera w konstruktorze (enumy); w widokach i reszcie
 * kodu — helpery `text_*` (src/helpers.php).
 */
final class PackageText
{
    public const string NAMESPACE = 'polygon';

    /**
     * Tekst `polygon::<klucz>`; brak tłumaczenia zwraca klucz bez przestrzeni.
     *
     * @param array<string, mixed> $replace
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $namespaced = self::NAMESPACE . '::' . $key;
        $text = self::translator()->text($namespaced, $replace, $locale);

        return $text !== $namespaced ? $text : $key;
    }

    /**
     * Etykieta kolumny modelu (docs/baza-danych.md §5): `data.<model>.<kolumna>`, potem `data.<kolumna>`; bez obu —
     * klucz modelu (jak text_data()).
     *
     * @param array<string, mixed> $replace
     */
    public static function data(object|string $model, string $column, array $replace = [], ?string $locale = null): string
    {
        return self::translator()->data($model, $column, $replace, $locale);
    }

    private static function translator(): TextTranslator
    {
        return Container::getInstance()->make(TextTranslator::class);
    }
}
