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

use Simplysmart\Polygon\Permissions\RequiredRole;

/**
 * Łączy strefy ze wszystkich plików instalacji w kolejności plików: uprawnienia się sumują, rola z późniejszego pliku
 * zastępuje wcześniejszą, konta są dopisywane; potem sprawdza role obowiązkowe (RequiredRole), role kont i nadaje
 * rolę root (`hero`) wszystkim uprawnieniom strefy (docs/instalacja.md).
 */
final readonly class ZoneInstallMerger
{
    private const string SOURCE = 'install files';

    /**
     * @param list<ZoneInstall> $parsed Strefy w kolejności plików.
     *
     * @return list<ZoneInstall>
     *
     * @throws InvalidInstallFile Gdy strefie brakuje roli obowiązkowej, konto powtarza się w plikach albo ma rolę
     *                             nieokreśloną w żadnym pliku.
     */
    public function merge(array $parsed): array
    {
        $zones = [];

        foreach ($parsed as $zone) {
            $previous = $zones[$zone->zone] ?? new ZoneInstall($zone->zone, [], [], []);
            $emails = array_map(static fn(InstallAccount $account): string => $account->email, $previous->accounts);

            foreach ($zone->accounts as $account) {
                if (in_array($account->email, $emails, true)) {
                    throw InvalidInstallFile::in(self::SOURCE, "account [{$account->email}] of zone [{$zone->zone}] is defined twice.");
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
                    throw InvalidInstallFile::in(self::SOURCE, "zone [{$zone->zone}] must define the role [{$required->value}].");
                }
            }

            foreach ($zone->accounts as $account) {
                foreach (array_diff($account->roles, array_keys($zone->roles)) as $missing) {
                    throw InvalidInstallFile::in(self::SOURCE, "account [{$account->email}] of zone [{$zone->zone}] has the undefined role [{$missing}].");
                }
            }

            return new ZoneInstall($zone->zone, $zone->permissions, [...$zone->roles, RequiredRole::ROOT->value => $zone->permissions], $zone->accounts);
        }, $zones));
    }
}
