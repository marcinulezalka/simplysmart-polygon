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
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Pozycja przełącznika kontekstu konta (Contracts\CurrentOrganization::contexts()): organizacja, rola (w strefie
 * z kontekstem roli — `role_contexts`; inaczej null), czy para jest bieżąca i czy jest domyślnym kontekstem konta po
 * zalogowaniu (DefaultOrganization).
 */
final readonly class OrganizationContext
{
    public function __construct(
        public Organization $organization,
        public bool $current,
        public bool $default = false,
        public ?string $role = null,
    ) {}

    /**
     * Nazwa kontekstu do pokazania: nazwa organizacji (Organization::contextName()), z rolą — „organizacja — rola”
     * (etykieta roli strefy, ZoneDefinition::roleLabel()).
     */
    public function label(ZoneDefinition $zone): string
    {
        $name = $this->organization->contextName();

        return $this->role === null ? $name : $name . ' — ' . $zone->roleLabel($this->role);
    }
}
