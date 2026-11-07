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

namespace Simplysmart\Polygon\Console;

use Illuminate\Console\Command;
use Simplysmart\Polygon\Install\Installer;
use Simplysmart\Polygon\Install\InvalidInstallFile;

/**
 * Stosuje domyślną konfigurację systemu z plików instalacji (`polygon:install`): strefy, uprawnienia, role i konta
 * startowe (docs/instalacja.md). Idempotentna; uruchamia ją też `php artisan db:seed`.
 */
final class InstallCommand extends Command
{
    protected $signature = 'polygon:install';

    protected $description = 'Zakłada uprawnienia, role i konta startowe z plików instalacji (JSON)';

    public function handle(Installer $installer): int
    {
        try {
            $report = $installer->install();
        } catch (InvalidInstallFile $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($report->organizationsCreated as $name) {
            $this->info("Organizacja założona: {$name}.");
        }

        foreach ($report->organizationsKept as $name) {
            $this->line("Organizacja już istnieje: {$name} (bez zmian).");
        }

        foreach ($report->resourcesCreated as $name) {
            $this->info("Zasób założony: {$name}.");
        }

        foreach ($report->resourcesKept as $name) {
            $this->line("Zasób już istnieje: {$name} (bez zmian).");
        }

        foreach ($report->leasesCreated as $description) {
            $this->info("Najem założony: {$description}.");
        }

        foreach ($report->leasesKept as $description) {
            $this->line("Najem już istnieje: {$description} (bez zmian).");
        }

        foreach ($report->created as $email) {
            $this->info("Konto założone: {$email} (PIN przy pierwszym logowaniu).");
        }

        foreach ($report->kept as $email) {
            $this->line("Konto już istnieje: {$email} (role uzupełnione).");
        }

        foreach ($report->schedulesCreated as $description) {
            $this->info("Grafik założony: {$description}.");
        }

        foreach ($report->schedulesKept as $description) {
            $this->line("Grafik już istnieje: {$description} (bez zmian).");
        }

        $this->info('Instalacja zakończona.');

        return self::SUCCESS;
    }
}
