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
use Simplysmart\Polygon\Providers\PolygonServiceProvider;

/**
 * Źródła dokumentacji: polygon (`wiki/`, `docs/`, `preview/` w katalogu głównym paczki, zawsze pierwsze), potem źródła
 * paczek dodane przez add() w `register()` ich providerów (`callAfterResolving(DocumentationSources::class, …)`).
 * Singleton (Providers\DocumentationServiceProvider).
 */
final class DocumentationSources
{
    /** Katalog główny paczki (nad `src/`). */
    private const string ROOT = PolygonServiceProvider::ROOT . '/..';

    /**
     * @var list<DocumentationSource>
     */
    private array $sources;

    public function __construct()
    {
        $this->sources = array_map(static fn(DocumentationSystem $system): DocumentationSource => new DocumentationSource(
            system: $system,
            key: DocumentationSource::POLYGON,
            path: self::ROOT . '/' . $system->value,
            assets: $system === DocumentationSystem::Preview ? ['polygon' => DocumentationAssets::EXAMPLE] : [],
        ), DocumentationSystem::cases());
    }

    /**
     * @throws InvalidArgumentException Gdy źródło o tym kluczu jest już w tym systemie.
     */
    public function add(DocumentationSource $source): void
    {
        foreach ($this->sources as $existing) {
            if ($existing->system === $source->system && $existing->key === $source->key) {
                throw new InvalidArgumentException("Documentation source [{$source->key}] of [{$source->system->value}] is already registered.");
            }
        }

        $this->sources[] = $source;
    }

    /**
     * Źródła systemu widoczne w strefie ($zone — nazwa strefy; null — poza strefą): źródło ze strefą tylko w niej.
     *
     * @return list<DocumentationSource>
     */
    public function visible(DocumentationSystem $system, ?string $zone): array
    {
        return array_values(array_filter($this->sources, static fn(DocumentationSource $source): bool => $source->system === $system && ($source->zone === null || $source->zone === $zone)));
    }
}
