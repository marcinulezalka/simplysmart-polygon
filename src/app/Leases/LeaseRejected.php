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

namespace Simplysmart\Polygon\Leases;

use RuntimeException;

/**
 * Umowa najmu nie została zapisana (LeaseConflict); reasons() — komunikaty do pokazania przy formularzu.
 */
final class LeaseRejected extends RuntimeException
{
    /**
     * @param list<LeaseConflict> $conflicts
     */
    public function __construct(
        public readonly array $conflicts,
    ) {
        parent::__construct(implode(' ', $this->reasons()));
    }

    /**
     * @return list<string>
     */
    public function reasons(): array
    {
        return array_map(static fn(LeaseConflict $conflict): string => $conflict->label(), $this->conflicts);
    }
}
