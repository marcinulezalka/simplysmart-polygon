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
 * Nagłówek strony w spisie treści (kolumna 3).
 */
final readonly class DocumentationHeading
{
    public function __construct(
        public int $level,
        public string $id,
        public string $text,
    ) {}
}
