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

/**
 * Wyszukiwanie stron po adresie — tylko wśród plików źródeł widocznych w strefie.
 */
final readonly class DocumentationPages
{
    public function __construct(
        private DocumentationSources $sources,
    ) {}

    /**
     * Strona o adresie `<strona>` (polygon) albo `<klucz>/<strona>` (paczka); null, gdy nie istnieje albo źródło nie
     * jest widoczne w strefie.
     */
    public function find(DocumentationSystem $system, string $path, ?string $zone): ?DocumentationPage
    {
        [$key, $slug] = str_contains($path, '/') ? explode('/', $path, 2) : [DocumentationSource::POLYGON, $path];

        foreach ($this->sources->visible($system, $zone) as $source) {
            $file = $source->key === $key && $source->pagePath($slug) === $path ? $source->file($slug) : null;

            if ($file !== null) {
                return new DocumentationPage($source, $slug, $file);
            }
        }

        return null;
    }

    /**
     * Pierwsza strona systemu (pierwsza pozycja spisu pierwszego źródła, które ją ma); null w systemie bez stron.
     */
    public function first(DocumentationSystem $system, ?string $zone): ?DocumentationPage
    {
        return $this->inOrder($system, $zone)[0] ?? null;
    }

    /**
     * Strony systemu w kolejności spisów, bez powtórzeń.
     *
     * @return list<DocumentationPage>
     */
    public function inOrder(DocumentationSystem $system, ?string $zone): array
    {
        $pages = [];

        foreach ($this->sources->visible($system, $zone) as $source) {
            $files = $source->files();

            foreach ($source->index()->slugs() as $slug) {
                if (isset($files[$slug])) {
                    $pages[] = new DocumentationPage($source, $slug, $files[$slug]);
                }
            }
        }

        return $pages;
    }
}
