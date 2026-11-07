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
use LogicException;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\ZoneAccount;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role konta w organizacji (zespół spatie = organizacja, PermissionTables::TEAM_FOREIGN_KEY): nadaje i odczytuje role
 * w podanej organizacji niezależnie od zespołu bieżącego żądania, który potem przywraca. Organizacja null to role
 * globalne — hero i konta bez organizacji (docs/baza-danych.md §15).
 */
final readonly class OrganizationRoles
{
    public function __construct(
        private PermissionRegistrar $registrar,
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

        $this->within($account, $organizationId, static fn(): ZoneAccount => $account->assignRole($roles));
    }

    /**
     * Nazwy ról konta w organizacji (null — role globalne), posortowane.
     *
     * @return list<string>
     */
    public function names(ZoneAccount $account, ?int $organizationId): array
    {
        $names = $this->within($account, $organizationId, static function () use ($account): array {
            $names = [];

            foreach ($account->getRoleNames() as $name) {
                if (is_string($name)) {
                    $names[] = $name;
                }
            }

            return $names;
        });

        sort($names);

        return $names;
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

    private function forgetLoadedRoles(?ZoneAccount $account): void
    {
        $account?->unsetRelation('roles')->unsetRelation('permissions');
    }
}
