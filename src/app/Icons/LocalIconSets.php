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

namespace Simplysmart\Polygon\Icons;

use Simplysmart\Polygon\Providers\PolygonServiceProvider;

/**
 * Pełne zestawy Iconify pobrane raz z npm (`@iconify-json/*`, docs/ikony.md) i serwowane statycznie z
 * `src/public/icons/<prefix>.json` (Providers\AssetsServiceProvider rejestruje cały `src/public/` paczki pod
 * `public/vendor/polygon/`). has()/get() czytają je bez HTTP — IconCatalog::exists()/svg() wołają to najpierw,
 * zanim sięgną po API Iconify. get() pamięta wczytane kolekcje w tablicy statycznej (plik ma kilka MB, nie
 * parsujemy go kilka razy w jednym żądaniu); pod długo żyjącym workerem przeżyje między żądaniami, co jest
 * nieszkodliwe, bo pliki są niezmienne po deployu — to pamięć procesu, nie stan żądania.
 */
final class LocalIconSets
{
    /** Podkatalog `src/public/` z pełnymi zestawami. */
    public const string DIRECTORY = 'icons';

    /** @var array<string, IconifyCollection|null> */
    private static array $loaded = [];

    public static function has(string $prefix): bool
    {
        return is_file(self::path($prefix));
    }

    public static function get(string $prefix): ?IconifyCollection
    {
        if (array_key_exists($prefix, self::$loaded)) {
            return self::$loaded[$prefix];
        }

        return self::$loaded[$prefix] = self::has($prefix) ? IconifyCollection::load(self::path($prefix)) : null;
    }

    private static function path(string $prefix): string
    {
        return PolygonServiceProvider::ROOT . '/public/' . self::DIRECTORY . '/' . $prefix . '.json';
    }
}
