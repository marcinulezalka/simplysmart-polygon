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

namespace Simplysmart\Polygon\Queries\Organizations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\ResourceKind;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;

/**
 * Zasoby dostępne dla organizacji: aktywne zasoby jej samej i jej przodków (np. gabinet siedziby dostępny w poradni),
 * opcjonalnie jednego rodzaju, po `sort_order` i nazwie. Wyszukiwanie po tokenie i sprawdzenie id działają w tym
 * samym zakresie, więc formularz jednostki nie wskaże zasobu cudzej organizacji.
 */
final readonly class OrganizationResources
{
    public function __construct(
        private OrganizationUnits $units,
    ) {}

    /**
     * @return Collection<int, OrganizationResource>
     */
    public function available(Organization $organization, ?ResourceKind $kind = null): Collection
    {
        $query = $this->query($organization, $kind);
        $query->orderBy(ColumnNames::SORT_ORDER);
        $query->orderBy(ColumnNames::NAME);
        $query->orderBy(ColumnNames::ID);

        return $query->get();
    }

    public function findByToken(Organization $organization, string $token, ?ResourceKind $kind = null): ?OrganizationResource
    {
        return $this->query($organization, $kind)->where(ColumnNames::TOKEN, $token)->first();
    }

    public function includes(Organization $organization, int $resourceId, ?ResourceKind $kind = null): bool
    {
        return $this->query($organization, $kind)->whereKey($resourceId)->exists();
    }

    /**
     * Id organizacji i jej przodków, od niej w górę (OrganizationUnits::lineage()).
     *
     * @return list<int>
     */
    public function lineage(Organization $organization): array
    {
        return $this->units->lineage($organization);
    }

    /**
     * @return Builder<OrganizationResource>
     */
    private function query(Organization $organization, ?ResourceKind $kind): Builder
    {
        $query = OrganizationResource::query();
        $query->whereIn(Organization::FOREIGN_KEY, $this->lineage($organization));
        $query->where(ColumnNames::IS_ACTIVE, true);

        if ($kind !== null) {
            $query->where(OrganizationResource::KIND, $kind->value);
        }

        return $query;
    }
}
