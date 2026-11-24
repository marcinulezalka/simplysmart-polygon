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
 * Grupa pozycji spisu bazowego.
 */
final readonly class DocumentationGroup
{
    /**
     * @param list<DocumentationItem> $items
     */
    public function __construct(
        public string $title,
        public array $items,
    ) {}
}
