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

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;
use Simplysmart\Polygon\PolygonConfig;

/**
 * Katalog ikon Iconify (docs/ikony.md): zestawy dozwolone do wyboru przez użytkownika (`polygon.icons.sets`) i API
 * Iconify (`polygon.icons.api`) — sprawdzenie, czy ikona istnieje (exists(), wynik w cache: istniejąca na CACHE_FOUND,
 * brakująca na CACHE_MISSING; API nieosiągalne — null), wycinek zestawu w formacie Iconify JSON (subset() — lokalne
 * kolekcje stref) i SVG w kolorze i rozmiarze (svg() — PNG do e-maili, polygon:icons:png).
 */
final readonly class IconCatalog
{
    /** Ile sekund pamiętać, że ikona istnieje. */
    public const int CACHE_FOUND = 2_592_000;

    /** Ile sekund pamiętać, że ikony nie ma. */
    public const int CACHE_MISSING = 3_600;

    /** Limit czasu zapytania do API w sekundach. */
    private const int TIMEOUT = 5;

    public function __construct(
        private Config $config,
        private Http $http,
        private Cache $cache,
    ) {}

    /**
     * @return list<string> Prefiksy zestawów dozwolonych do wyboru.
     */
    public function sets(): array
    {
        $sets = $this->config->get(PolygonConfig::ICON_SETS);

        return is_array($sets) ? array_values(array_filter($sets, is_string(...))) : [];
    }

    public function allows(IconifyIcon $icon): bool
    {
        return in_array($icon->prefix, $this->sets(), true);
    }

    public function api(): string
    {
        $api = $this->config->get(PolygonConfig::ICON_API);

        return is_string($api) && $api !== '' ? $api : 'https://api.iconify.design';
    }

    /**
     * Czy ikona istnieje w API Iconify; null, gdy API nie odpowiada (wtedy wynik nie trafia do cache).
     */
    public function exists(IconifyIcon $icon): ?bool
    {
        $key = 'polygon.icons.exists.' . $icon->value();
        $cached = $this->cache->get($key);

        if (is_bool($cached)) {
            return $cached;
        }

        try {
            $response = $this->http->timeout(self::TIMEOUT)->acceptJson()->get($this->api() . '/' . $icon->prefix . '.json', ['icons' => $icon->name]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->serverError()) {
            return null;
        }

        $icons = $response->successful() ? $response->json('icons') : null;
        $aliases = $response->successful() ? $response->json('aliases') : null;
        $found = (is_array($icons) && array_key_exists($icon->name, $icons)) || (is_array($aliases) && array_key_exists($icon->name, $aliases));
        $this->cache->put($key, $found, $found ? self::CACHE_FOUND : self::CACHE_MISSING);

        return $found;
    }

    /**
     * Wycinek zestawu z API w formacie Iconify JSON (ikony i ich aliasy) — do lokalnej kolekcji strefy.
     *
     * @param list<string> $names
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException Gdy API nie odpowiada albo którejś ikony nie ma.
     */
    public function subset(string $prefix, array $names): array
    {
        $response = $this->http->timeout(self::TIMEOUT)->acceptJson()->get($this->api() . '/' . $prefix . '.json', ['icons' => implode(',', $names)]);
        $data = $response->successful() ? $response->json() : null;

        if (! is_array($data) || ! is_array($data['icons'] ?? null)) {
            throw new RuntimeException("Iconify API did not return the [{$prefix}] icon set.");
        }

        $missing = is_array($data['not_found'] ?? null) ? array_values(array_filter($data['not_found'], is_string(...))) : [];

        if ($missing !== []) {
            throw new RuntimeException("Iconify set [{$prefix}] has no icons: " . implode(', ', $missing) . '.');
        }

        unset($data['lastModified']);

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * SVG ikony z API w kolorze i wysokości w pikselach (`color` — np. `#1d4873`).
     *
     * @throws RuntimeException Gdy API nie zwróciło SVG.
     */
    public function svg(IconifyIcon $icon, string $color, int $height): string
    {
        $response = $this->http->timeout(self::TIMEOUT)->get($this->api() . '/' . $icon->prefix . '/' . $icon->name . '.svg', ['color' => $color, 'height' => $height]);
        $svg = $response->successful() ? $response->body() : '';

        return str_starts_with(ltrim($svg), '<svg') ? $svg : throw new RuntimeException("Iconify API has no icon [{$icon->value()}].");
    }
}
