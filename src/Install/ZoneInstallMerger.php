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

use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Permissions\RequiredRole;

/**
 * Łączy pliki instalacji w kolejności plików: katalog ról (`roles`, późniejszy plik zastępuje rolę), a w strefach
 * uprawnienia się sumują, rola z późniejszego pliku zastępuje wcześniejszą, konta są dopisywane. Każda zarejestrowana
 * strefa z kontami dostaje role katalogu (`"*"` = wszystkie jej uprawnienia, inaczej żadne), które jej własne role
 * nadpisują; potem sprawdza role obowiązkowe (RequiredRole), role kont i nadaje rolę root (`hero`) wszystkim
 * uprawnieniom strefy (docs/instalacja.md).
 */
final readonly class ZoneInstallMerger
{
    public function __construct(
        private ZoneRegistry $registry,
    ) {}

    /**
     * @param list<InstallFile> $files Pliki w kolejności stosowania.
     *
     * @return list<ZoneInstall>
     *
     * @throws InvalidInstallFile Gdy strefie brakuje roli obowiązkowej, konto powtarza się w plikach albo ma rolę
     *                             nieokreśloną w żadnym pliku.
     */
    public function merge(array $files): array
    {
        $catalog = [];
        $zones = [];

        foreach ($this->registry->all() as $name => $definition) {
            if ($definition->model !== null) {
                $zones[$name] = new ZoneInstall($name, [], [], []);
            }
        }

        foreach ($files as $file) {
            $catalog = [...$catalog, ...$file->catalog];

            foreach ($file->zones as $zone) {
                $zones[$zone->zone] = $this->combine($zones[$zone->zone] ?? new ZoneInstall($zone->zone, [], [], []), $zone);
            }
        }

        return array_values(array_map(fn(ZoneInstall $zone): ZoneInstall => $this->complete($zone, $catalog), $zones));
    }

    private function combine(ZoneInstall $previous, ZoneInstall $zone): ZoneInstall
    {
        $emails = array_map(static fn(InstallAccount $account): string => $account->email, $previous->accounts);

        foreach ($zone->accounts as $account) {
            if (in_array($account->email, $emails, true)) {
                throw InvalidInstallFile::merged("account [{$account->email}] of zone [{$zone->zone}] is defined twice.");
            }
        }

        return new ZoneInstall(
            $zone->zone,
            array_values(array_unique([...$previous->permissions, ...$zone->permissions])),
            [...$previous->roles, ...$zone->roles],
            [...$previous->accounts, ...$zone->accounts],
        );
    }

    /**
     * @param array<string, bool> $catalog
     */
    private function complete(ZoneInstall $zone, array $catalog): ZoneInstall
    {
        $roles = [...array_map(static fn(bool $all): array => $all ? $zone->permissions : [], $catalog), ...$zone->roles];

        foreach (RequiredRole::cases() as $required) {
            if (! array_key_exists($required->value, $roles)) {
                throw InvalidInstallFile::merged("zone [{$zone->zone}] must define the role [{$required->value}].");
            }
        }

        foreach ($zone->accounts as $account) {
            foreach (array_diff($account->allRoles(), array_keys($roles)) as $missing) {
                throw InvalidInstallFile::merged("account [{$account->email}] of zone [{$zone->zone}] has the undefined role [{$missing}].");
            }
        }

        return new ZoneInstall($zone->zone, $zone->permissions, [...$roles, RequiredRole::ROOT->value => $zone->permissions], $zone->accounts);
    }
}
