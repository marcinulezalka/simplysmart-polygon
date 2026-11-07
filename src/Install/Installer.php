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
use Spatie\Permission\PermissionRegistrar;

/**
 * Domyślna konfiguracja systemu z plików instalacji (InstallFiles): najpierw waliduje wszystkie pliki
 * (InstallFileParser) i łączy strefy (ZoneInstallMerger) oraz organizacje (InstallOrganizations), potem w jednej
 * transakcji zakłada brakujące organizacje (OrganizationInstaller) i każdą strefę z uprawnieniami, rolami i kontami
 * startowymi (ZoneInstaller). Idempotentny: wolno go uruchomić ponownie i na produkcji; uprawnień, ról i członkostw
 * spoza plików nie usuwa.
 */
final readonly class Installer
{
    public function __construct(
        private InstallFiles $files,
        private InstallFileParser $parser,
        private ZoneInstallMerger $merger,
        private InstallOrganizations $organizations,
        private ConnectionInterface $db,
        private OrganizationInstaller $organizationInstaller,
        private ZoneInstaller $zoneInstaller,
        private PermissionRegistrar $permissions,
    ) {}

    /**
     * @throws InvalidInstallFile Gdy któryś plik jest błędny (nic nie zostało zapisane).
     */
    public function install(): InstallReport
    {
        $files = array_map($this->parser->parse(...), $this->files->all());
        $zones = $this->merger->merge($files);
        $organizations = $this->organizations->merge($files, $zones);

        $report = $this->db->transaction(function () use ($zones, $organizations): InstallReport {
            $report = new InstallReport();
            $installed = $this->organizationInstaller->install($organizations, $report);

            foreach ($zones as $zone) {
                $this->zoneInstaller->install($zone, $report, $installed);
            }

            return $report;
        });

        $this->permissions->forgetCachedPermissions();

        return $report;
    }
}
