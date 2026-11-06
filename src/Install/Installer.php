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
use Simplysmart\Polygon\Auth\CreateAccount;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Database\IdSlots;
use Simplysmart\Polygon\Models\Permissions\Permission;
use Simplysmart\Polygon\Models\Permissions\Role;
use Simplysmart\Polygon\Models\PolygonAccount;
use Simplysmart\Polygon\Permissions\PermissionTables;
use Simplysmart\Polygon\Queries\Accounts\ZoneAccounts;
use Spatie\Permission\PermissionRegistrar;

/**
 * Domyślna konfiguracja systemu z plików instalacji (InstallFiles): najpierw waliduje wszystkie pliki
 * (InstallFileParser) i łączy strefy (ZoneInstallMerger), potem w jednej transakcji zakłada uprawnienia i role guarda
 * strefy (role dostają dokładnie uprawnienia z pliku) oraz konta startowe
 * (tylko brakujące, przez Auth\CreateAccount; losowe id z puli seederów przy `"id": "random"`), dopisując im role
 * z pliku. Idempotentny: wolno go uruchomić ponownie i na produkcji; uprawnień i ról spoza plików nie usuwa.
 */
final readonly class Installer
{
    public function __construct(
        private InstallFiles $files,
        private InstallFileParser $parser,
        private ZoneInstallMerger $merger,
        private ConnectionInterface $db,
        private CreateAccount $accounts,
        private ZoneAccounts $zoneAccounts,
        private IdSlots $slots,
        private PermissionRegistrar $permissions,
    ) {}

    /**
     * @return InstallReport
     *
     * @throws InvalidInstallFile Gdy któryś plik jest błędny (nic nie zostało zapisane).
     */
    public function install(): InstallReport
    {
        $parsed = [];

        foreach ($this->files->all() as $path) {
            $parsed = [...$parsed, ...$this->parser->parse($path)];
        }

        $zones = $this->merger->merge($parsed);

        $report = $this->db->transaction(function () use ($zones): InstallReport {
            $report = new InstallReport();

            foreach ($zones as $zone) {
                $this->installZone($zone, $report);
            }

            return $report;
        });

        $this->permissions->forgetCachedPermissions();

        return $report;
    }

    private function installZone(ZoneInstall $zone, InstallReport $report): void
    {
        foreach ($zone->permissions as $name) {
            $this->entry(Permission::class, $name, $zone->zone);
        }

        // Spatie szuka uprawnień ról w cache rejestru: nowe uprawnienia muszą być w nim przed syncPermissions().
        $this->permissions->forgetCachedPermissions();

        foreach ($zone->roles as $name => $permissions) {
            $this->entry(Role::class, $name, $zone->zone)->syncPermissions($permissions);
        }

        foreach ($zone->accounts as $account) {
            $existing = $this->zoneAccounts->findByEmail($account->email);

            if ($existing === null) {
                $id = $account->randomId ? $this->slots->random(PolygonAccount::TABLE) : null;
                $this->accounts->handle($account->name, $account->email, $account->roles, $id);
                $report->created[] = $account->email;

                continue;
            }

            $existing->assignRole($account->roles);
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
