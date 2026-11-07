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
use Simplysmart\Polygon\Models\OrganizationResource;

/**
 * Zasoby, których właścicielem jest organizacja (tabela `organization_resources`); zasoby dostępne także z organizacji
 * nadrzędnych daje Queries\Organizations\OrganizationResources.
 */
trait HasResources
{
    /**
     * @return HasMany<OrganizationResource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(OrganizationResource::class, Organization::FOREIGN_KEY);
    }
}
