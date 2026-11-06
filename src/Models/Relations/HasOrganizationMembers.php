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

namespace Simplysmart\Polygon\Models\Relations;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationMember;

/**
 * Członkostwa organizacji (tabela `organization_members`, konta wszystkich stref).
 */
trait HasOrganizationMembers
{
    /**
     * @return HasMany<OrganizationMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class, Organization::FOREIGN_KEY);
    }
}
