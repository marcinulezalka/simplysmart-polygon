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

namespace Simplysmart\Polygon\Documentation;

use InvalidArgumentException;

/**
 * Katalog stron jednego systemu dokumentacji jednej paczki: pliki `<strona>.md` i spis `documentation.md`. Strony
 * polygonu mają adres `<strona>`, pozostałych paczek `<klucz>/<strona>`.
 */
final readonly class DocumentationSource
{
    /** Klucz źródeł polygonu (strony bez segmentu klucza). */
    public const string POLYGON = 'polygon';

    /** Spis bazowy w katalogu źródła. */
    public const string INDEX = 'documentation';

    /** Wzorzec klucza źródła i nazwy strony. */
    public const string SLUG_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    private const string EXTENSION = '.md';

    /**
     * @param string $path Katalog ze stronami (ścieżka absolutna).
     * @param string|null $zone Strefa, której konta widzą źródło; w Docs wymagana dla każdej paczki poza polygonem.
     * @param array<string, list<string>> $assets Assety przykładów Preview: paczka (Assets\PackageAssets) => wejścia.
     *
     * @throws InvalidArgumentException Gdy klucz jest błędny albo Docs paczki nie mają strefy.
     */
    public function __construct(
        public DocumentationSystem $system,
        public string $key,
        public string $path,
        public ?string $zone = null,
        public array $assets = [],
    ) {
        if (preg_match('/^' . self::SLUG_PATTERN . '$/D', $key) !== 1) {
            throw new InvalidArgumentException("Documentation source key [{$key}] must match " . self::SLUG_PATTERN . '.');
        }

        if ($system === DocumentationSystem::Docs && $zone === null && $key !== self::POLYGON) {
            throw new InvalidArgumentException("Documentation source [{$key}] of Docs must name its zone.");
        }
    }

    /**
     * Adres strony w systemie: `<strona>` albo `<klucz>/<strona>`.
     */
    public function pagePath(string $slug): string
    {
        return $this->key === self::POLYGON ? $slug : $this->key . '/' . $slug;
    }

    /**
     * Plik strony z listy plików źródła (nie ze ścieżki z adresu); null, gdy takiej strony nie ma.
     */
    public function file(string $slug): ?string
    {
        return $this->files()[$slug] ?? null;
    }

    /**
     * Strony źródła (bez spisu i plików o innych nazwach, np. `README.md`).
     *
     * @return array<string, string> Nazwa strony => plik.
     */
    public function files(): array
    {
        $files = [];

        foreach (glob($this->path . '/*' . self::EXTENSION) ?: [] as $file) {
            $slug = basename($file, self::EXTENSION);

            if ($slug !== self::INDEX && preg_match('/^' . self::SLUG_PATTERN . '$/D', $slug) === 1) {
                $files[$slug] = $file;
            }
        }

        return $files;
    }

    /**
     * Spis bazowy źródła; pusty, gdy nie ma pliku `documentation.md`.
     */
    public function index(): DocumentationIndex
    {
        $file = $this->path . '/' . self::INDEX . self::EXTENSION;

        return DocumentationIndex::parse(is_file($file) ? (string) file_get_contents($file) : '');
    }
}
