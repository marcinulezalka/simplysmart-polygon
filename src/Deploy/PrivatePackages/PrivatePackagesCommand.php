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

namespace Simplysmart\Polygon\Deploy\PrivatePackages;

use Simplysmart\Polygon\Deploy\Console\ExitCode;
use Simplysmart\Polygon\Deploy\Console\Output;

/**
 * Polecenie `vendor/bin/composer-private` (`composer private:install` projektu): instaluje paczki prywatne
 * z git-ignorowanego `private-packages.json` przez `composer.private.json` (albo pokazuje plan przy --dry-run).
 * Bez listy kończy się sukcesem — publiczny klon nic nie instaluje. Z `--deploy` zamiast instalacji buduje manifest
 * wdrożeniowy (DeployLock).
 */
final readonly class PrivatePackagesCommand
{
    public const string USAGE = <<<'USAGE'
        Użycie: composer-private [--deploy] [--dry-run] [--root=<katalog>]

          --deploy          manifest wdrożeniowy: composer.deploy.json i composer.deploy.lock z paczkami
                            z repozytoriów git (zamiast katalogów path), bez instalacji
          --dry-run         pokazuje paczki; nie zapisuje plików i nie uruchamia composera
          --root=<katalog>  katalog projektu (domyślnie bieżący)
        USAGE;

    public const string DEPLOY = '--deploy';

    public const string DRY_RUN_NOTICE = 'Tryb --dry-run: pliki nie zostały zmienione.';

    private const string DRY_RUN = '--dry-run';

    private const string ROOT = '--root=';

    public function __construct(
        private ComposerUpdate $composer,
        private DeployLock $deployLock,
    ) {}

    /**
     * @param list<string> $arguments Argumenty bez nazwy skryptu.
     * @param string $workingDirectory Domyślny katalog projektu.
     *
     * @return int Kod wyjścia: ExitCode::SUCCESS — sukces lub brak paczek, ExitCode::FAILURE — błąd, inny — kod composera.
     */
    public function run(array $arguments, string $workingDirectory, Output $output): int
    {
        $root = $workingDirectory;
        $dryRun = false;
        $deploy = false;

        foreach ($arguments as $argument) {
            if ($argument === self::DRY_RUN) {
                $dryRun = true;
            } elseif ($argument === self::DEPLOY) {
                $deploy = true;
            } elseif (str_starts_with($argument, self::ROOT)) {
                $root = substr($argument, strlen(self::ROOT));
            } else {
                $output->error("Nieznana opcja: {$argument}\n" . self::USAGE);

                return ExitCode::FAILURE;
            }
        }

        try {
            $private = new PrivatePackages($root);

            if ($deploy) {
                return $this->deployLock->build($private, $dryRun, $output);
            }

            $packages = $private->packages();

            if ($packages === []) {
                $output->line('Brak paczek prywatnych (' . PrivatePackages::LIST . ') — nic do zainstalowania.');

                return ExitCode::SUCCESS;
            }

            $summary = 'Paczki prywatne: ' . implode(', ', array_keys($packages)) . '.';

            if ($dryRun) {
                $private->manifest($packages);
                $output->line("{$summary} " . self::DRY_RUN_NOTICE);

                return ExitCode::SUCCESS;
            }

            $private->prepare($packages);
            $output->line($summary);

            return $this->composer->run($private->root(), PrivatePackages::MANIFEST, array_keys($packages));
        } catch (PrivatePackagesException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::FAILURE;
        }
    }
}
