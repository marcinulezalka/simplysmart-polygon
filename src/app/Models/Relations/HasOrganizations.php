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

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationMember;

/**
 * Organizacje, których członkiem jest konto strefy (tabela `organization_members`).
 */
trait HasOrganizations
{
    /**
     * @return MorphToMany<Organization, $this>
     */
    public function organizations(): MorphToMany
    {
        return $this->morphToMany(
            Organization::class,
            OrganizationMember::MEMBER_MORPH,
            OrganizationMember::TABLE,
            OrganizationMember::MEMBER_ID,
            Organization::FOREIGN_KEY,
        )->withTimestamps();
    }
}
