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

use Simplysmart\Polygon\Models\Organization;

/**
 * Organizacje ze wszystkich plików instalacji: plik stosowany później zastępuje organizację o tym samym kluczu (jak
 * role). Sprawdza organizacje nadrzędne (istnieją albo to organizacja hero — Organization::HERO_KEY; bez cykli) i organizacje kont stref, zanim cokolwiek trafi do bazy,
 * i zwraca organizacje w kolejności zakładania — organizacja nadrzędna przed podrzędną.
 */
final readonly class InstallOrganizations
{
    /**
     * @param list<InstallFile> $files Pliki w kolejności stosowania.
     * @param list<ZoneInstall> $zones Strefy po połączeniu plików (konta z organizacjami).
     *
     * @return list<InstallOrganization>
     *
     * @throws InvalidInstallFile Gdy organizacja nadrzędna albo organizacja konta nie jest określona w żadnym pliku,
     *                            albo organizacje tworzą cykl.
     */
    public function merge(array $files, array $zones): array
    {
        $organizations = [];

        foreach ($files as $file) {
            foreach ($file->organizations as $organization) {
                $organizations[$organization->key] = $organization;
            }
        }

        foreach ($zones as $zone) {
            foreach ($zone->accounts as $account) {
                foreach ($account->organizations as $key) {
                    if (! array_key_exists($key, $organizations)) {
                        throw InvalidInstallFile::merged("account [{$account->email}] of zone [{$zone->zone}] belongs to the undefined organization [{$key}].");
                    }
                }
            }
        }

        $ordered = [];

        foreach (array_keys($organizations) as $key) {
            $this->append($organizations, $key, [], $ordered);
        }

        return array_values($ordered);
    }

    /**
     * Dopisuje organizację po jej organizacjach nadrzędnych.
     *
     * @param array<string, InstallOrganization> $organizations
     * @param list<string> $trail Klucze na ścieżce do organizacji (wykrywanie cykli).
     * @param array<string, InstallOrganization> $ordered
     */
    private function append(array $organizations, string $key, array $trail, array &$ordered): void
    {
        if (array_key_exists($key, $ordered)) {
            return;
        }

        if (in_array($key, $trail, true)) {
            throw InvalidInstallFile::merged('organizations form a cycle: ' . implode(' > ', [...$trail, $key]) . '.');
        }

        $organization = $organizations[$key];

        if ($organization->parent !== null && $organization->parent !== Organization::HERO_KEY) {
            if (! array_key_exists($organization->parent, $organizations)) {
                throw InvalidInstallFile::merged("organization [{$key}] has the undefined parent [{$organization->parent}].");
            }

            $this->append($organizations, $organization->parent, [...$trail, $key], $ordered);
        }

        $ordered[$key] = $organization;
    }
}
