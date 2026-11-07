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

namespace Simplysmart\Polygon\Install;

use Simplysmart\Polygon\Auth\CreateZoneAccount;
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Database\IdSlots;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\Permissions\Permission;
use Simplysmart\Polygon\Models\Permissions\Role;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Organizations\OrganizationMembership;
use Simplysmart\Polygon\Permissions\OrganizationRoles;
use Simplysmart\Polygon\Permissions\PermissionTables;
use Simplysmart\Polygon\Queries\Accounts\ZoneAccounts;
use Spatie\Permission\PermissionRegistrar;

/**
 * Zakłada jedną strefę z połączonych plików instalacji: brakujące uprawnienia i role guarda strefy (role dostają
 * dokładnie uprawnienia z pliku) oraz konta startowe (tylko brakujące, modelem strefy przez Auth\CreateZoneAccount;
 * losowe id z puli seederów przy `"id": "random"`), dopisując im role i członkostwa organizacji z pliku (konto z rolą
 * hero zostaje też członkiem organizacji hero): role globalne (InstallAccount::globalRoles()) i role w każdej
 * organizacji konta (InstallAccount::rolesIn(), zespół spatie = organizacja). Uprawnień, ról i członkostw spoza plików
 * nie usuwa. Atrybuty konta z pliku (`attributes`) trafiają do jego `json_attributes` (InstallAttributes) — także
 * istniejącego. Działa w transakcji instalatora (Installer).
 */
final readonly class ZoneInstaller
{
    public function __construct(
        private ZoneRegistry $zones,
        private CreateZoneAccount $accounts,
        private ZoneAccounts $zoneAccounts,
        private IdSlots $slots,
        private PermissionRegistrar $permissions,
        private OrganizationMembership $membership,
        private OrganizationRoles $roles,
        private InstallAttributes $attributes,
    ) {}

    /**
     * @param array<string, Organization> $installed Organizacje z plików (klucz => organizacja, OrganizationInstaller).
     */
    public function install(ZoneInstall $zone, InstallReport $report, array $installed): void
    {
        foreach ($zone->permissions as $name) {
            $this->entry(Permission::class, $name, $zone->zone);
        }

        // Spatie szuka uprawnień ról w cache rejestru: nowe uprawnienia muszą być w nim przed syncPermissions().
        $this->permissions->forgetCachedPermissions();

        foreach ($zone->roles as $name => $permissions) {
            $this->entry(Role::class, $name, $zone->zone)->syncPermissions($permissions);
        }

        $definition = $this->zones->get($zone->zone);
        $model = $definition->accountModel();

        foreach ($zone->accounts as $account) {
            $existing = $this->zoneAccounts->findByEmail($definition, $account->email);

            if ($existing === null) {
                $id = $account->randomId ? $this->slots->random((new $model())->getTable()) : null;
                $created = $this->accounts->handle($definition, $account->name, $account->email, $account->globalRoles(), $id);
                $this->joinOrganizations($created, $account, $installed);
                $this->attributes->merge($created, $account->attributes);
                $report->created[] = $account->email;

                continue;
            }

            $this->roles->assign($existing, $account->globalRoles(), null);
            $this->membership->joinHeroOrganization($existing, $account->globalRoles());
            $this->joinOrganizations($existing, $account, $installed);
            $this->attributes->merge($existing, $account->attributes);
            $report->kept[] = $account->email;
        }
    }

    /**
     * Konto staje się członkiem (personelem) organizacji z pliku i dostaje w każdej z nich swoje role; członkostw i ról
     * spoza plików instalacja nie odbiera.
     *
     * @param array<string, Organization> $installed
     */
    private function joinOrganizations(ZoneAccount $account, InstallAccount $definition, array $installed): void
    {
        foreach ($definition->organizations as $key) {
            $organization = $installed[$key];
            $this->membership->add($account, $organization);
            $this->roles->assignIn($account, $definition->rolesIn($key), $organization);
        }
    }

    /**
     * Uprawnienie albo rola guarda o tej nazwie (także usunięte, bo nazwa zostaje zajęta); brakujące zakłada aktywne.
     *
     * @template TModel of Permission|Role
     *
     * @param class-string<TModel> $model
     *
     * @return TModel
     */
    private function entry(string $model, string $name, string $guard): Permission|Role
    {
        $entry = $model::withTrashed()->where(ColumnNames::NAME, $name)->where(PermissionTables::GUARD_NAME, $guard)->first() ?? new $model();

        if (! $entry->exists) {
            $entry->forceFill([ColumnNames::NAME => $name, PermissionTables::GUARD_NAME => $guard, ColumnNames::IS_ACTIVE => true])->save();
        }

        return $entry;
    }
}
