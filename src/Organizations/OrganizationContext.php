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

namespace Simplysmart\Polygon\Organizations;

use Simplysmart\Polygon\Models\Organization;

/**
 * Pozycja przełącznika organizacji konta (Contracts\CurrentOrganization::contexts()): organizacja i czy jest bieżąca.
 */
final readonly class OrganizationContext
{
    public function __construct(
        public Organization $organization,
        public bool $current,
    ) {}
}
