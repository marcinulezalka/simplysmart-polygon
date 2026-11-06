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

use Illuminate\Database\Eloquent\Model;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationMember;
use Simplysmart\Polygon\Permissions\RequiredRole;

/**
 * Członkostwo konta strefy w organizacji (bez ról — polygon nie ma zespołów spatie): dodanie, usunięcie i automatyczne
 * dołączenie kont z rolą hero (RequiredRole::ROOT) do organizacji hero (Organization::HERO_ID).
 */
final readonly class OrganizationMembership
{
    /**
     * Dodaje członka; istniejące członkostwo zostaje bez zmian.
     */
    public function add(Model $account, Organization $organization): void
    {
        $member = $this->member($account, $organization);

        if (! OrganizationMember::query()->where($member)->exists()) {
            (new OrganizationMember())->forceFill($member)->save();
        }
    }

    public function remove(Model $account, Organization $organization): void
    {
        OrganizationMember::query()->where($this->member($account, $organization))->delete();
    }

    /**
     * Konto, któremu nadano rolę hero, staje się członkiem organizacji hero, o ile ją założono (HeroOrganizationSeeder
     * w seederze głównym, przed plikami instalacji); bez niej (albo w koszu) nic się nie zmienia.
     *
     * @param list<string> $roles Role nadane kontu.
     */
    public function joinHeroOrganization(Model $account, array $roles): void
    {
        if (! in_array(RequiredRole::ROOT->value, $roles, true)) {
            return;
        }

        $hero = Organization::query()->find(Organization::HERO_ID);

        if ($hero !== null) {
            $this->add($account, $hero);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function member(Model $account, Organization $organization): array
    {
        return [Organization::FOREIGN_KEY => $organization->getKey(), ...OrganizationMember::keyOf($account)];
    }
}
