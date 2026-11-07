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

use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationUnitType;

/**
 * Zakłada organizacje z plików instalacji, rozpoznawane po kluczu (`json_attributes.install_key`, także w koszu):
 * brakująca powstaje aktywna, istniejąca zostaje bez zmian (zmiany z aplikacji nie są cofane). `parent` =
 * Organization::HERO_KEY wpina organizację pod organizację hero (HERO_ID, niewidoczną we frontendzie stref). Zasoby
 * organizacji (także istniejącej) zakłada ResourceInstaller. Działa w transakcji instalatora (Installer).
 */
final readonly class OrganizationInstaller
{
    public function __construct(
        private ResourceInstaller $resources,
    ) {}

    /** Klucz organizacji z pliku instalacji w `json_attributes` organizacji. */
    public const string INSTALL_KEY = 'install_key';

    /**
     * @param list<InstallOrganization> $organizations Organizacja nadrzędna przed podrzędną (InstallOrganizations).
     *
     * @return array<string, Organization> Klucz => organizacja (z organizacją hero, gdy któraś się pod nią wpina).
     *
     * @throws InvalidInstallFile Gdy organizacji hero albo typu jednostki o podanym kodzie nie ma (transakcja jest wycofywana).
     */
    public function install(array $organizations, InstallReport $report): array
    {
        $installed = [];

        if (in_array(Organization::HERO_KEY, array_map(static fn(InstallOrganization $organization): ?string => $organization->parent, $organizations), true)) {
            $installed[Organization::HERO_KEY] = Organization::withTrashed()->find(Organization::HERO_ID)
                ?? throw InvalidInstallFile::merged('the hero organization does not exist (run php artisan polygon:organizations:seed).');
        }

        foreach ($organizations as $organization) {
            $existing = Organization::withTrashed()->where(ColumnNames::JSON_ATTRIBUTES . '->' . self::INSTALL_KEY, $organization->key)->first();

            if ($existing !== null) {
                $installed[$organization->key] = $existing;
                $report->organizationsKept[] = $existing->name;
            } else {
                $installed[$organization->key] = $this->create($organization, $installed);
                $report->organizationsCreated[] = $organization->name;
            }

            $this->resources->install($installed[$organization->key], $organization->resources, $report);
        }

        return $installed;
    }

    /**
     * @param array<string, Organization> $installed Organizacje założone wcześniej (z organizacją nadrzędną).
     *
     * @throws InvalidInstallFile Gdy typu jednostki o podanym kodzie nie ma.
     */
    private function create(InstallOrganization $organization, array $installed): Organization
    {
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

        return $created;
    }

    /**
     * @throws InvalidInstallFile Gdy typu jednostki o tym kodzie nie ma.
     */
    private function unitType(InstallOrganization $organization): int
    {
        $id = OrganizationUnitType::query()->where(OrganizationUnitType::CODE, $organization->unitType)->value(ColumnNames::ID);

        return is_int($id) ? $id : throw InvalidInstallFile::merged("organization [{$organization->key}] has the unknown unit type [{$organization->unitType}].");
    }
}
