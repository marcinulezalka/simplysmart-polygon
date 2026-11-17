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

namespace Simplysmart\Polygon\Permissions;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use LogicException;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\ZoneAccount;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role konta w organizacji (zespół spatie = organizacja, PermissionTables::TEAM_FOREIGN_KEY): nadaje i odczytuje role
 * w podanej organizacji niezależnie od zespołu bieżącego żądania, który potem przywraca. Organizacja null to role
 * globalne — hero i konta bez organizacji (docs/baza-danych.md §15). Nadawanie i odczyt ról działają na wszystkich
 * rolach konta, także gdy żądanie zawęża je do aktywnej roli (Permissions\ActiveRole::bypass()).
 */
final readonly class OrganizationRoles
{
    /** Relacja uprawnień roli spatie (liczona przy wyborze najszerszej roli). */
    private const string PERMISSIONS_RELATION = 'permissions';

    public function __construct(
        private PermissionRegistrar $registrar,
        private ActiveRole $activeRole,
    ) {}

    /**
     * Dopisuje kontu role w zapisanej organizacji; nadanych wcześniej nie odbiera.
     *
     * @param list<string> $roles Nazwy istniejących ról guarda konta.
     *
     * @throws LogicException Gdy organizacja nie ma całkowitego id (niezapisana) — role nie mogą wtedy trafić do ról globalnych.
     */
    public function assignIn(ZoneAccount $account, array $roles, Organization $organization): void
    {
        $id = $organization->getKey();

        if (! is_int($id)) {
            throw new LogicException("Organization [{$organization->name}] has no integer id: its roles cannot be assigned.");
        }

        $this->assign($account, $roles, $id);
    }

    /**
     * Dopisuje kontu role w organizacji; nadanych wcześniej nie odbiera.
     *
     * @param list<string> $roles Nazwy istniejących ról guarda konta.
     * @param int|null $organizationId Organizacja przypisania; null — role globalne.
     */
    public function assign(ZoneAccount $account, array $roles, ?int $organizationId): void
    {
        if ($roles === []) {
            return;
        }

        $this->activeRole->bypass(fn(): ZoneAccount => $this->within($account, $organizationId, static fn(): ZoneAccount => $account->assignRole($roles)));
    }

    /**
     * Nazwy ról konta w organizacji (null — role globalne), posortowane.
     *
     * @return list<string>
     */
    public function names(ZoneAccount $account, ?int $organizationId): array
    {
        $names = array_keys($this->permissionCounts($account, $organizationId));
        sort($names);

        return $names;
    }

    /**
     * Role konta w organizacji (null — role globalne) od najważniejszej — z największą liczbą uprawnień, przy remisie
     * po nazwie (np. właściciel, kierownik; lekarz przed rejestracją). Kolejność kontekstów roli konta
     * (Organizations\SessionCurrentOrganization::contexts()).
     *
     * @return list<string>
     */
    public function ranked(ZoneAccount $account, ?int $organizationId): array
    {
        $counts = $this->permissionCounts($account, $organizationId);
        $names = array_keys($counts);
        usort($names, static fn(string $first, string $second): int => [$counts[$second], $first] <=> [$counts[$first], $second]);

        return $names;
    }

    /**
     * Najszersza rola konta w organizacji — pierwsza z ranked(); null, gdy konto nie ma w niej ról. Domyślna rola
     * kontekstu konta (Organizations\SessionCurrentOrganization), gdy nie wybrało ani nie zapisało innej.
     */
    public function broadest(ZoneAccount $account, ?int $organizationId): ?string
    {
        return $this->ranked($account, $organizationId)[0] ?? null;
    }

    /**
     * Aktywne konta modelu strefy z rolą w organizacji (np. lekarze poradni: rola `doctor` w niej), po nazwie. Role
     * globalne i role w innych organizacjach się nie liczą.
     *
     * @template TAccount of ZoneAccount
     *
     * @param class-string<TAccount> $model Model kont strefy.
     *
     * @return Collection<int, TAccount>
     */
    public function holders(string $model, string $role, int $organizationId): Collection
    {
        $account = new $model();
        $assignments = $account->getConnection()->table(PermissionTables::MODEL_HAS_ROLES);
        $assignments->join(PermissionTables::ROLES, PermissionTables::ROLES . '.' . ColumnNames::ID, '=', PermissionTables::MODEL_HAS_ROLES . '.' . PermissionTables::ROLE_FOREIGN_KEY);
        $assignments->where(PermissionTables::MODEL_HAS_ROLES . '.' . PermissionTables::TEAM_FOREIGN_KEY, $organizationId);
        $assignments->where(PermissionTables::MODEL_HAS_ROLES . '.' . PermissionTables::MODEL_TYPE, $account->getMorphClass());
        $assignments->where(PermissionTables::ROLES . '.' . ColumnNames::NAME, $role);

        $query = $model::query();
        $query->whereKey($assignments->pluck(PermissionTables::MODEL_HAS_ROLES . '.' . PermissionTables::MODEL_ID)->all());
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->orderBy(ColumnNames::NAME);
        $query->orderBy(ColumnNames::ID);

        return $query->get();
    }

    /**
     * Uruchamia działanie w zespole organizacji (null — role globalne) i przywraca poprzedni zespół; role konta
     * (gdy jest) wczytują się od nowa przed i po, bo relacja spatie nie zna zmiany zespołu.
     *
     * @template TResult
     *
     * @param Closure(): TResult $action
     *
     * @return TResult
     */
    public function within(?ZoneAccount $account, ?int $organizationId, Closure $action): mixed
    {
        $previous = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($organizationId);
        $this->forgetLoadedRoles($account);

        try {
            return $action();
        } finally {
            $this->registrar->setPermissionsTeamId($previous);
            $this->forgetLoadedRoles($account);
        }
    }

    /**
     * Wszystkie role konta w organizacji (bez zawężenia do aktywnej roli) z liczbą uprawnień każdej.
     *
     * @return array<string, int>
     */
    private function permissionCounts(ZoneAccount $account, ?int $organizationId): array
    {
        return $this->activeRole->bypass(fn(): array => $this->within($account, $organizationId, static function () use ($account): array {
            $counts = [];

            foreach ($account->roles()->withCount(self::PERMISSIONS_RELATION)->get() as $role) {
                $name = $role->getAttribute(ColumnNames::NAME);
                $count = $role->getAttribute(self::PERMISSIONS_RELATION . '_count');

                if (is_string($name)) {
                    $counts[$name] = is_numeric($count) ? (int) $count : 0;
                }
            }

            return $counts;
        }));
    }

    private function forgetLoadedRoles(?ZoneAccount $account): void
    {
        $account?->unsetRelation('roles')->unsetRelation('permissions');
    }
}
