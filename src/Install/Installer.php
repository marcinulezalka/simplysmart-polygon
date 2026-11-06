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
use Simplysmart\Polygon\Auth\AccountColumns;
use Simplysmart\Polygon\Auth\CreateAccount;
use Simplysmart\Polygon\Auth\EmailAddress;
use Simplysmart\Polygon\Contracts\BlindIndex;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Database\IdSlots;
use Simplysmart\Polygon\Models\Permissions\Permission;
use Simplysmart\Polygon\Models\Permissions\Role;
use Simplysmart\Polygon\Models\PolygonAccount;
use Simplysmart\Polygon\Permissions\RequiredRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Domyślna konfiguracja systemu z plików instalacji (InstallFiles): najpierw waliduje wszystkie pliki i łączy strefy
 * (uprawnienia sumowane, rola z późniejszego pliku zastępuje wcześniejszą, konta dopisywane; root `hero` zawsze ze
 * wszystkimi uprawnieniami strefy; każda strefa z rolami obowiązkowymi RequiredRole), potem w jednej transakcji zakłada uprawnienia i role guarda strefy (role dostają dokładnie uprawnienia z pliku) oraz konta startowe
 * (tylko brakujące, przez Auth\CreateAccount; losowe id z puli seederów przy `"id": "random"`), dopisując im role
 * z pliku. Idempotentny: wolno go uruchomić ponownie i na produkcji; uprawnień i ról spoza plików nie usuwa.
 */
final readonly class Installer
{
    public function __construct(
        private InstallFiles $files,
        private InstallFileParser $parser,
        private ConnectionInterface $db,
        private CreateAccount $accounts,
        private BlindIndex $index,
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

        $zones = $this->merged($parsed);

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

    /**
     * @param list<ZoneInstall> $parsed Strefy w kolejności plików.
     *
     * @return list<ZoneInstall>
     *
     * @throws InvalidInstallFile Gdy strefie brakuje roli obowiązkowej, konto powtarza się w plikach albo ma rolę
     *                             nieokreśloną w żadnym pliku.
     */
    private function merged(array $parsed): array
    {
        $zones = [];

        foreach ($parsed as $zone) {
            $previous = $zones[$zone->zone] ?? new ZoneInstall($zone->zone, [], [], []);
            $emails = array_map(static fn(InstallAccount $account): string => $account->email, $previous->accounts);

            foreach ($zone->accounts as $account) {
                if (in_array($account->email, $emails, true)) {
                    throw InvalidInstallFile::in('install files', "account [{$account->email}] of zone [{$zone->zone}] is defined twice.");
                }
            }

            $zones[$zone->zone] = new ZoneInstall(
                $zone->zone,
                array_values(array_unique([...$previous->permissions, ...$zone->permissions])),
                [...$previous->roles, ...$zone->roles],
                [...$previous->accounts, ...$zone->accounts],
            );
        }

        return array_values(array_map(static function (ZoneInstall $zone): ZoneInstall {
            foreach (RequiredRole::cases() as $required) {
                if (! array_key_exists($required->value, $zone->roles)) {
                    throw InvalidInstallFile::in('install files', "zone [{$zone->zone}] must define the role [{$required->value}].");
                }
            }

            foreach ($zone->accounts as $account) {
                foreach (array_diff($account->roles, array_keys($zone->roles)) as $missing) {
                    throw InvalidInstallFile::in('install files', "account [{$account->email}] of zone [{$zone->zone}] has the undefined role [{$missing}].");
                }
            }

            return new ZoneInstall($zone->zone, $zone->permissions, [...$zone->roles, RequiredRole::ROOT->value => $zone->permissions], $zone->accounts);
        }, $zones));
    }

    private function installZone(ZoneInstall $zone, InstallReport $report): void
    {
        foreach ($zone->permissions as $name) {
            $this->permission($name, $zone->zone);
        }

        $this->permissions->forgetCachedPermissions();

        foreach ($zone->roles as $name => $permissions) {
            $this->role($name, $zone->zone)->syncPermissions($permissions);
        }

        foreach ($zone->accounts as $account) {
            $existing = PolygonAccount::withTrashed()->where(AccountColumns::EMAIL_HASH, $this->index->hash(EmailAddress::normalize($account->email)))->first();

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

    private function permission(string $name, string $guard): Permission
    {
        $permission = Permission::withTrashed()->where(ColumnNames::NAME, $name)->where('guard_name', $guard)->first() ?? new Permission();

        if (! $permission->exists) {
            $permission->forceFill([ColumnNames::NAME => $name, 'guard_name' => $guard, ColumnNames::IS_ACTIVE => true])->save();
        }

        return $permission;
    }

    private function role(string $name, string $guard): Role
    {
        $role = Role::withTrashed()->where(ColumnNames::NAME, $name)->where('guard_name', $guard)->first() ?? new Role();

        if (! $role->exists) {
            $role->forceFill([ColumnNames::NAME => $name, 'guard_name' => $guard, ColumnNames::IS_ACTIVE => true])->save();
        }

        return $role;
    }
}
