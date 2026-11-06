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

namespace Simplysmart\Polygon\Database\Seeders;

use Illuminate\Database\Seeder;
use Simplysmart\Polygon\Install\Installer;

/**
 * Domyślna konfiguracja systemu z plików instalacji (Install\Installer): uprawnienia, role i konta startowe stref.
 * Seeder referencyjny, idempotentny, także na produkcji.
 */
final class InstallSeeder extends Seeder
{
    public function __construct(
        private readonly Installer $installer,
    ) {}

    public function run(): void
    {
        $report = $this->installer->install();

        foreach ($report->created as $email) {
            $this->command?->info("Konto założone: {$email}");
        }
    }
}
