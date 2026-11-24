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
 * Istniejąca strona źródła.
 */
final readonly class DocumentationPage
{
    public function __construct(
        public DocumentationSource $source,
        public string $slug,
        public string $file,
    ) {}

    /**
     * Adres strony w systemie (DocumentationSource::pagePath()).
     */
    public function path(): string
    {
        return $this->source->pagePath($this->slug);
    }
}
