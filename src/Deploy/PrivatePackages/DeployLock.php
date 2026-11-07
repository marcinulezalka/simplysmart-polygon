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
 * Świeży manifest wdrożeniowy projektu (`composer.deploy.json` i `composer.deploy.lock`): stary jest usuwany,
 * paczki z repozytoriów path wymagane przez projekt dostają repozytoria git (`origin`), a Composer zapisuje lock
 * bez instalacji i bez skryptów projektu (`vendor/` zostaje bez zmian). Projekt bez paczek z repozytoriów path
 * nie potrzebuje manifestu — serwer instaluje z `composer.lock`.
 */
final readonly class DeployLock
{
    /** Lock bez instalacji paczek i bez skryptów projektu. */
    private const array COMPOSER_OPTIONS = ['--no-install', '--no-scripts'];

    public function __construct(
        private ComposerUpdate $composer,
        private RepositorySource $repositories,
    ) {}

    /**
     * Paczki z repozytoriów path wymagane przez projekt, które mają commity niewypchnięte do `origin`.
     *
     * @return list<string> Nazwy paczek.
     *
     * @throws PrivatePackagesException Gdy lista paczek albo manifest jest nieczytelny.
     */
    public function unpushedPackages(PrivatePackages $private): array
    {
        $deploy = new DeployManifest($private->root(), $this->repositories);

        return $deploy->unpushed($deploy->packages($private->packages()));
    }

    /**
     * Wypycha paczki z repozytoriów path wymagane przez projekt, które mają commity niewypchnięte do `origin`, zanim
     * manifest wdrożeniowy przypnie je do commitów z repozytoriów zdalnych.
     *
     * @return list<string> Nazwy wypchniętych paczek.
     *
     * @throws PrivatePackagesException Gdy lista paczek jest nieczytelna albo `git push` paczki się nie powiódł.
     */
    public function pushPackages(PrivatePackages $private, Output $output): array
    {
        $deploy = new DeployManifest($private->root(), $this->repositories);
        $sources = $deploy->packages($private->packages());
        $unpushed = $deploy->unpushed($sources);

        foreach ($unpushed as $name) {
            $output->line("Wypycham {$name}.");

            if (! $this->repositories->push($sources[$name])) {
                throw new PrivatePackagesException("Nie udało się wypchnąć paczki {$name} ({$sources[$name]}, git push). Sprawdź dostęp do origin i uruchom ponownie.");
            }
        }

        return $unpushed;
    }

    /**
     * @param  bool  $dryRun  Tylko sprawdza paczki i ich repozytoria; nie zapisuje plików i nie uruchamia composera.
     * @return int Kod wyjścia: ExitCode::SUCCESS albo kod composera (po błędzie manifestu nie ma).
     *
     * @throws PrivatePackagesException Gdy lista paczek albo manifest jest nieczytelny, albo paczka nie ma `origin`.
     */
    public function build(PrivatePackages $private, bool $dryRun, Output $output): int
    {
        if (! $dryRun) {
            $private->removeDeploy();
        }

        $packages = $private->packages();
        $deploy = new DeployManifest($private->root(), $this->repositories);
        $sources = $deploy->packages($packages);

        if ($sources === []) {
            $output->line('Brak paczek z repozytoriów path — manifest wdrożeniowy niepotrzebny (' . PrivatePackages::PUBLIC_LOCK . ').');

            return ExitCode::SUCCESS;
        }

        $manifest = $deploy->manifest($packages, $sources);

        foreach ($deploy->unpushed($sources) as $name) {
            $output->error("Uwaga: {$name} ma niewypchnięte commity — lock wskaże kod z repozytorium zdalnego.");
        }

        $summary = 'Paczki z repozytoriów git: ' . implode(', ', array_keys($sources)) . '.';

        if ($dryRun) {
            $output->line("{$summary} " . PrivatePackagesCommand::DRY_RUN_NOTICE);

            return ExitCode::SUCCESS;
        }

        $private->prepareDeploy($manifest);
        $output->line($summary);
        $exitCode = $this->composer->run($private->root(), PrivatePackages::DEPLOY_MANIFEST, array_keys($sources), self::COMPOSER_OPTIONS);

        if ($exitCode !== ExitCode::SUCCESS) {
            $private->removeDeploy();
        }

        return $exitCode;
    }
}
