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
use Illuminate\Database\Eloquent\Model;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationMember;

/**
 * Organizacje, w których kontekście może pracować konto strefy: członkostwa w organizacjach aktywnych i nieusuniętych
 * (od najstarszego). Członek organizacji hero (Organization::HERO_ID) jest hero — dostaje organizację hero i po niej
 * wszystkie pozostałe aktywne organizacje (po nazwie).
 */
final class ActiveMemberships
{
    /**
     * @return list<int>
     */
    public function ids(Model $account): array
    {
        $ids = self::integers($this->member($account)
            ->whereIn(Organization::FOREIGN_KEY, $this->activeIds())
            ->orderBy(ColumnNames::ID)
            ->pluck(Organization::FOREIGN_KEY)
            ->all());

        if (! in_array(Organization::HERO_ID, $ids, true)) {
            return $ids;
        }

        $others = Organization::query()
            ->where(ColumnNames::IS_ACTIVE, true)
            ->whereKeyNot(Organization::HERO_ID)
            ->orderBy(ColumnNames::NAME)
            ->orderBy(ColumnNames::ID)
            ->pluck(ColumnNames::ID)
            ->all();

        return [Organization::HERO_ID, ...self::integers($others)];
    }

    /**
     * Organizacje z ids() w tej samej kolejności, z organizacją nadrzędną (Organization::contextName()).
     *
     * @return list<Organization>
     */
    public function organizations(Model $account): array
    {
        $ids = $this->ids($account);
        $organizations = Organization::query()->with('parent')->whereKey($ids)->get()->keyBy(ColumnNames::ID);
        $ordered = [];

        foreach ($ids as $id) {
            $organization = $organizations->get($id);

            if ($organization instanceof Organization) {
                $ordered[] = $organization;
            }
        }

        return $ordered;
    }

    public function includes(Model $account, int $organizationId): bool
    {
        return in_array($organizationId, $this->ids($account), true);
    }

    /**
     * Czy konto jest członkiem (aktywnej) organizacji hero.
     */
    public function isHero(Model $account): bool
    {
        return $this->member($account)
            ->where(Organization::FOREIGN_KEY, Organization::HERO_ID)
            ->whereIn(Organization::FOREIGN_KEY, $this->activeIds())
            ->exists();
    }

    /**
     * @return Builder<OrganizationMember>
     */
    private function member(Model $account): Builder
    {
        $query = OrganizationMember::query();
        $query->where(OrganizationMember::keyOf($account));

        return $query;
    }

    /**
     * Podzapytanie id organizacji aktywnych i nieusuniętych.
     *
     * @return Builder<Organization>
     */
    private function activeIds(): Builder
    {
        $query = Organization::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->select(ColumnNames::ID);

        return $query;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<int>
     */
    private static function integers(array $values): array
    {
        return array_values(array_map(intval(...), array_filter($values, is_numeric(...))));
    }
}
