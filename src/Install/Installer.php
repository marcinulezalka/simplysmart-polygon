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

use Illuminate\Database\ConnectionInterface;
use Simplysmart\Polygon\Auth\CreateZoneAccount;
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Database\IdSlots;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationUnitType;
use Simplysmart\Polygon\Models\Permissions\Permission;
use Simplysmart\Polygon\Models\Permissions\Role;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Organizations\OrganizationMembership;
use Simplysmart\Polygon\Permissions\OrganizationRoles;
use Simplysmart\Polygon\Permissions\PermissionTables;
use Simplysmart\Polygon\Queries\Accounts\ZoneAccounts;
use Spatie\Permission\PermissionRegistrar;

/**
 * Domyślna konfiguracja systemu z plików instalacji (InstallFiles): najpierw waliduje wszystkie pliki
 * (InstallFileParser) i łączy strefy (ZoneInstallMerger) oraz organizacje (InstallOrganizations), potem w jednej
 * transakcji zakłada brakujące organizacje (po kluczu z pliku, organizacja nadrzędna przed podrzędną; istniejących nie
 * zmienia), uprawnienia i role guarda strefy (role dostają dokładnie uprawnienia z pliku) oraz konta startowe
 * (tylko brakujące, modelem strefy przez Auth\CreateZoneAccount; losowe id z puli seederów przy `"id": "random"`), dopisując im role
 * i członkostwa organizacji z pliku (konto z rolą hero zostaje też członkiem organizacji hero): role globalne
 * (InstallAccount::globalRoles()) i role w każdej organizacji konta (InstallAccount::rolesIn(), zespół spatie = organizacja). Idempotentny: wolno go uruchomić ponownie i na produkcji; uprawnień, ról i członkostw spoza plików nie usuwa.
 */
final readonly class Installer
{
    /** Klucz organizacji z pliku instalacji w `json_attributes` organizacji. */
    public const string INSTALL_KEY = 'install_key';

    private const string UNIT_TYPE_CODE = 'code';

    private const string SOURCE = 'install files';

    public function __construct(
        private InstallFiles $files,
        private InstallFileParser $parser,
        private ZoneInstallMerger $merger,
        private ConnectionInterface $db,
        private ZoneRegistry $zones,
        private CreateZoneAccount $accounts,
        private ZoneAccounts $zoneAccounts,
        private IdSlots $slots,
        private PermissionRegistrar $permissions,
        private OrganizationMembership $membership,
        private InstallOrganizations $organizations,
        private OrganizationRoles $roles,
    ) {}

    /**
     * @return InstallReport
     *
     * @throws InvalidInstallFile Gdy któryś plik jest błędny (nic nie zostało zapisane).
     */
    public function install(): InstallReport
    {
        $files = array_map($this->parser->parse(...), $this->files->all());
        $zones = $this->merger->merge($files);
        $organizations = $this->organizations->merge($files, $zones);

        $report = $this->db->transaction(function () use ($zones, $organizations): InstallReport {
            $report = new InstallReport();
            $installed = $this->installOrganizations($organizations, $report);

            foreach ($zones as $zone) {
                $this->installZone($zone, $report, $installed);
            }

            return $report;
        });

        $this->permissions->forgetCachedPermissions();

        return $report;
    }

    /**
     * Organizacje z plików, rozpoznawane po kluczu (`json_attributes.install_key`, także w koszu): brakująca powstaje
     * aktywna, istniejąca zostaje bez zmian (zmiany z aplikacji nie są cofane). `parent` = Organization::HERO_KEY wpina
     * organizację pod organizację hero (HERO_ID, niewidoczną we frontendzie stref).
     *
     * @param list<InstallOrganization> $organizations Organizacja nadrzędna przed podrzędną (InstallOrganizations).
     *
     * @return array<string, Organization> Klucz => organizacja.
     *
     * @throws InvalidInstallFile Gdy typu jednostki o podanym kodzie nie ma (transakcja jest wycofywana).
     */
    private function installOrganizations(array $organizations, InstallReport $report): array
    {
        $installed = [];

        if (in_array(Organization::HERO_KEY, array_map(static fn(InstallOrganization $organization): ?string => $organization->parent, $organizations), true)) {
            $installed[Organization::HERO_KEY] = Organization::withTrashed()->find(Organization::HERO_ID)
                ?? throw InvalidInstallFile::in(self::SOURCE, 'the hero organization does not exist (run php artisan polygon:organizations:seed).');
        }

        foreach ($organizations as $organization) {
            $existing = Organization::withTrashed()->where(ColumnNames::JSON_ATTRIBUTES . '->' . self::INSTALL_KEY, $organization->key)->first();

            if ($existing !== null) {
                $installed[$organization->key] = $existing;
                $report->organizationsKept[] = $existing->name;

                continue;
            }

            $created = new Organization();
            $created->forceFill([
                ColumnNames::NAME => $organization->name,
                ColumnNames::IS_ACTIVE => true,
                ColumnNames::PARENT_ID => $organization->parent === null ? null : $installed[$organization->parent]->getKey(),
                Organization::SHORT_NAME => $organization->shortName,
                Organization::IS_DEPARTMENT => $organization->isDepartment,
                Organization::UNIT_TYPE_ID => $organization->unitType === null ? null : $this->unitType($organization),
                ColumnNames::JSON_ATTRIBUTES => [self::INSTALL_KEY => $organization->key],
                ...$organization->details,
            ])->save();

            $installed[$organization->key] = $created;
            $report->organizationsCreated[] = $organization->name;
        }

        return $installed;
    }

    /**
     * @throws InvalidInstallFile Gdy typu jednostki o tym kodzie nie ma.
     */
    private function unitType(InstallOrganization $organization): int
    {
        $id = OrganizationUnitType::query()->where(self::UNIT_TYPE_CODE, $organization->unitType)->value(ColumnNames::ID);

        return is_int($id) ? $id : throw InvalidInstallFile::in(self::SOURCE, "organization [{$organization->key}] has the unknown unit type [{$organization->unitType}].");
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
            $id = $organization->getKey();
            $this->roles->assign($account, $definition->rolesIn($key), is_int($id) ? $id : null);
        }
    }

    /**
     * @param array<string, Organization> $installed Organizacje z plików (klucz => organizacja).
     */
    private function installZone(ZoneInstall $zone, InstallReport $report, array $installed): void
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
                $report->created[] = $account->email;

                continue;
            }

            $this->roles->assign($existing, $account->globalRoles(), null);
            $this->membership->joinHeroOrganization($existing, $account->globalRoles());
            $this->joinOrganizations($existing, $account, $installed);
            $report->kept[] = $account->email;
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
