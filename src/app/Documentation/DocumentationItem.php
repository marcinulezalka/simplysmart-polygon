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
 * Pozycja spisu bazowego: strona źródła, opcjonalnie z kotwicą nagłówka.
 */
final readonly class DocumentationItem
{
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $anchor = null,
    ) {}
}
