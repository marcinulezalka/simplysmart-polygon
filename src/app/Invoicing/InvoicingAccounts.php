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

namespace Simplysmart\Polygon\Invoicing;

use Illuminate\Database\Eloquent\Collection;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\InvoicingAccount;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Queries\Organizations\OrganizationUnits;

/**
 * Konta systemu fakturowego (docs/baza-danych.md §21). Konto należy do organizacji głównej klienta — najwyższej nad
 * daną organizacją, bez organizacji hero (root()) — i obsługuje ją razem z jednostkami podrzędnymi: of() daje aktywne
 * konto dla dowolnej organizacji drzewa, forContractor() — konto, do którego należy kontrahent, organizationIds() —
 * id organizacji objętych kontem (kontrahenci tych organizacji są synchronizowani).
 */
final readonly class InvoicingAccounts
{
    public function __construct(
        private OrganizationUnits $units,
    ) {}

    public function root(Organization $organization): Organization
    {
        $lineage = array_values(array_filter($this->units->lineage($organization), static fn(int $id): bool => $id !== Organization::HERO_ID));
        $rootId = $lineage === [] ? $organization->id : $lineage[count($lineage) - 1];

        return $rootId === $organization->id ? $organization : Organization::query()->find($rootId) ?? $organization;
    }

    /**
     * Konto organizacji głównej, także wyłączone (ekran ustawień).
     */
    public function configured(Organization $organization): ?InvoicingAccount
    {
        $query = InvoicingAccount::query();
        $query->where(Organization::FOREIGN_KEY, $this->root($organization)->id);

        return $query->first();
    }

    public function of(Organization $organization): ?InvoicingAccount
    {
        $account = $this->configured($organization);

        return $account !== null && $account->is_active ? $account : null;
    }

    public function forContractor(Contractor $contractor): ?InvoicingAccount
    {
        $organization = $contractor->organization;

        return $organization === null ? null : $this->of($organization);
    }

    /**
     * @return Collection<int, InvoicingAccount>
     */
    public function active(): Collection
    {
        $query = InvoicingAccount::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->with('organization');
        $query->orderBy(ColumnNames::ID);

        return $query->get();
    }

    /**
     * Id organizacji objętych kontem: organizacja główna i jej aktywne jednostki podrzędne.
     *
     * @return list<int>
     */
    public function organizationIds(InvoicingAccount $account): array
    {
        $root = $account->organization;

        return $root === null ? [] : array_values(array_map(static fn(Organization $unit): int => $unit->id, $this->units->of($root)->all()));
    }
}
