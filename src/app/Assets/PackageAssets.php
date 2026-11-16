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

namespace Simplysmart\Polygon\Assets;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Assety paczek budowane ich własnym Vite (`vite.config.js` paczki, orkiestrator `bin/assets.mjs`) do `public/build/`
 * paczki z manifestem (`manifest.json`). Paczka rejestruje swój katalog `public/` w providerze (add()), widok wstawia
 * znaczniki wejść dyrektywą `@packageAssets('<paczka>', ['resources/scss/app.scss'])` (tags()). Manifest czytany jest
 * wprost z paczki, a adresy wskazują `public/vendor/<paczka>/build/…` — symlink tworzy `php artisan polygon:assets`.
 */
final class PackageAssets
{
    /** Katalog w `public/` projektu z assetami paczek (`public/vendor/<paczka>`). */
    public const string PUBLIC_DIRECTORY = 'vendor';

    /** Katalog builda w `public/` paczki. */
    public const string BUILD_DIRECTORY = 'build';

    /** Plik manifestu Vite w katalogu builda. */
    public const string MANIFEST = 'manifest.json';

    /**
     * @var array<string, string> Nazwa paczki => katalog `public/` paczki.
     */
    private array $packages = [];

    /**
     * @var array<string, array<string, array{file: string, css?: list<string>}>> Wczytane manifesty.
     */
    private array $manifests = [];

    /**
     * Rejestruje katalog `public/` paczki (np. `__DIR__ . '/../public'`).
     */
    public function add(string $package, string $publicPath): void
    {
        $this->packages[$package] = realpath($publicPath) ?: rtrim($publicPath, '/');
        unset($this->manifests[$package]);
    }

    /**
     * @return array<string, string> Nazwa paczki => katalog `public/` paczki.
     */
    public function packages(): array
    {
        return $this->packages;
    }

    /**
     * Znaczniki `<link rel="stylesheet">` i `<script type="module">` wejść z manifestu paczki.
     *
     * @param list<string> $entries Wejścia jak w `vite.config.js` paczki, np. `resources/scss/app.scss`.
     *
     * @throws InvalidArgumentException Gdy paczka nie jest zarejestrowana, nie ma builda albo wejścia w manifeście.
     */
    public function tags(string $package, array $entries): HtmlString
    {
        $manifest = $this->manifest($package);
        $tags = [];

        foreach ($entries as $entry) {
            $chunk = $manifest[$entry] ?? throw new InvalidArgumentException("Brak wejścia [{$entry}] w manifeście paczki [{$package}] — uruchom npm run build.");

            foreach ($chunk['css'] ?? [] as $css) {
                $tags[] = '<link rel="stylesheet" href="' . e($this->url($package, $css)) . '">';
            }

            $tags[] = str_ends_with($chunk['file'], '.css')
                ? '<link rel="stylesheet" href="' . e($this->url($package, $chunk['file'])) . '">'
                : '<script type="module" src="' . e($this->url($package, $chunk['file'])) . '"></script>';
        }

        return new HtmlString(implode("\n", array_unique($tags)));
    }

    /**
     * Adres pliku builda paczki w `public/vendor/<paczka>/build/`.
     */
    public function url(string $package, string $file): string
    {
        return asset(self::PUBLIC_DIRECTORY . '/' . $package . '/' . self::BUILD_DIRECTORY . '/' . $file);
    }

    /**
     * @return array<string, array{file: string, css?: list<string>}>
     */
    private function manifest(string $package): array
    {
        if (isset($this->manifests[$package])) {
            return $this->manifests[$package];
        }

        $public = $this->packages[$package] ?? throw new InvalidArgumentException("Paczka [{$package}] nie zarejestrowała assetów (PackageAssets::add()).");
        $path = $public . '/' . self::BUILD_DIRECTORY . '/' . self::MANIFEST;
        $contents = is_file($path) ? file_get_contents($path) : false;
        $manifest = $contents === false ? null : json_decode($contents, true);

        if (! is_array($manifest)) {
            throw new InvalidArgumentException("Brak manifestu [{$path}] — uruchom npm run build.");
        }

        $chunks = [];

        foreach ($manifest as $entry => $chunk) {
            if (is_string($entry) && is_array($chunk) && is_string($chunk['file'] ?? null)) {
                $css = array_values(array_filter(is_array($chunk['css'] ?? null) ? $chunk['css'] : [], is_string(...)));
                $chunks[$entry] = $css === [] ? ['file' => $chunk['file']] : ['file' => $chunk['file'], 'css' => $css];
            }
        }

        return $this->manifests[$package] = $chunks;
    }
}
