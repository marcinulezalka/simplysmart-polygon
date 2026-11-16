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

namespace Simplysmart\Polygon\Http;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Plik z treści w pamięci jako odpowiedź HTTP z nagłówkiem `Content-Disposition` według RFC 6266: nazwa pełna
 * w `filename*` (UTF-8) i zastępcza ASCII w `filename`. Nazwa może pochodzić od użytkownika (np. nazwa wgranego pliku),
 * więc znaki sterujące, ukośniki i cudzysłowy są zastępowane „-”. Odpowiedź nie trafia do pamięci podręcznej
 * (`no-store, private`), a przeglądarka nie zgaduje typu treści (`nosniff`).
 */
final class FileResponse
{
    /** Zamiennik znaków niedozwolonych w nazwie pliku. */
    private const string REPLACEMENT = '-';

    /** Znaki sterujące, ukośniki i cudzysłów — niedozwolone w nazwie pliku nagłówka. */
    private const string UNSAFE_NAME = '/[\x00-\x1F\x7F\/\\\\"]+/u';

    /** Znaki spoza bezpiecznego ASCII nazwy zastępczej (`%` i inne zabrania HeaderUtils). */
    private const string UNSAFE_FALLBACK = '/[^A-Za-z0-9._ -]+/';

    /** Nazwa, gdy po oczyszczeniu nic nie zostaje. */
    private const string DEFAULT_NAME = 'file';

    /**
     * Plik otwierany w przeglądarce (np. PDF, skan).
     */
    public static function inline(string $contents, string $mime, string $name): Response
    {
        return self::make(HeaderUtils::DISPOSITION_INLINE, $contents, $mime, $name);
    }

    /**
     * Plik do pobrania.
     */
    public static function attachment(string $contents, string $mime, string $name): Response
    {
        return self::make(HeaderUtils::DISPOSITION_ATTACHMENT, $contents, $mime, $name);
    }

    /**
     * Nazwa pliku bez znaków sterujących, ukośników i cudzysłowów; pusta po oczyszczeniu — DEFAULT_NAME.
     */
    public static function safeName(string $name): string
    {
        $clean = trim((string) preg_replace(self::UNSAFE_NAME, self::REPLACEMENT, trim($name)));

        return $clean === '' ? self::DEFAULT_NAME : $clean;
    }

    private static function make(string $disposition, string $contents, string $mime, string $name): Response
    {
        $filename = self::safeName($name);
        $fallback = trim((string) preg_replace(self::UNSAFE_FALLBACK, self::REPLACEMENT, Str::ascii($filename)));

        return new Response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename, $fallback === '' ? self::DEFAULT_NAME : $fallback),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
