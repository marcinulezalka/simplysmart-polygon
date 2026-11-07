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

namespace Simplysmart\Polygon\Deploy\Deployment;

use Simplysmart\Polygon\Deploy\Console\Confirmation;
use Simplysmart\Polygon\Deploy\Console\ExitCode;
use Simplysmart\Polygon\Deploy\Console\Output;
use Simplysmart\Polygon\Deploy\PrivatePackages\DeployLock;
use Simplysmart\Polygon\Deploy\PrivatePackages\PrivatePackages;
use Simplysmart\Polygon\Deploy\PrivatePackages\PrivatePackagesException;
use Simplysmart\Polygon\Deploy\Process\ProcessException;
use Simplysmart\Polygon\Deploy\Process\ProcessRunner;

/**
 * Polecenie `vendor/bin/deploy <projekt>`, uruchamiane na komputerze deweloperskim w repozytorium projektu: sprawdza, czy projekt jest wypchnięty,
 * aktualizuje przed-produkcję (katalog projektu na tym komputerze) skryptem `remote.sh`, a potem — zależnie od
 * ProductionMode — uruchamia ten sam skrypt na serwerze przez ssh. Błąd przed-produkcji zatrzymuje wdrożenie.
 *
 * Środowiska nie wysyła: na serwerze remote.sh kopiuje leżący tam `.env.production` jako `.env`, a `.env`
 * przed-produkcji zostaje bez zmian. Paczki instaluje `composer install --no-dev`: z manifestu wdrożeniowego
 * (DeployLock — paczki z repozytoriów path, także prywatne, z ich repozytoriów git), który deploy buduje na nowo,
 * kopiuje do przed-produkcji i wysyła na serwer (scp), a projekt bez paczek z repozytoriów path — z `composer.lock`.
 * Plik instalacji projektu (`<projekt>.json` obok `<projekt>.env`: konta, role, uprawnienia; docs/instalacja.md) trafia
 * tak samo do `storage/app/<projekt>.install.json` obu środowisk, a remote.sh stosuje go `polygon:install`.
 */
final readonly class DeployCommand
{
    public function __construct(
        private ProcessRunner $processes,
        private RepositoryCheck $repository,
        private RemoteScript $script,
        private Confirmation $confirmation,
        private DeployLock $deployLock,
    ) {}

    /**
     * @param  list<string>  $arguments  Argumenty bez nazwy skryptu.
     * @param  string  $workingDirectory  Domyślny katalog repozytorium projektu.
     * @return int Kod wyjścia: ExitCode::SUCCESS, ExitCode::FAILURE albo kod skryptu, który się nie powiódł.
     */
    public function run(array $arguments, string $workingDirectory, Output $output): int
    {
        try {
            $options = DeployOptions::fromArguments($arguments, $workingDirectory);

            return $this->deploy(ProjectConfig::load("{$options->root}/" . ProjectConfig::DIRECTORY, $options->project), $options, $output);
        } catch (DeployException|ProcessException|PrivatePackagesException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::FAILURE;
        }
    }

    /**
     * Wdrożenie projektu: repozytorium, przed-produkcja, decyzja o produkcji i produkcja.
     *
     * @throws DeployException|ProcessException|PrivatePackagesException
     */
    private function deploy(ProjectConfig $project, DeployOptions $options, Output $output): int
    {
        $private = new PrivatePackages($options->root);

        if ($options->withPush) {
            $this->push($private, $options, $output);
        }

        if (! $this->repositoryReady($options, $output)) {
            return ExitCode::FAILURE;
        }

        if ($options->dryRun) {
            $this->deployLock->build($private, true, $output);
            array_map($output->line(...), (new DeployPlan($project, $options->production))->lines());

            return ExitCode::SUCCESS;
        }

        $output->line('==> Tworzę manifest wdrożeniowy');
        $lock = $this->deployLock->build($private, false, $output);

        if ($lock !== ExitCode::SUCCESS) {
            $output->error("Manifest wdrożeniowy nie powstał (kod {$lock}) — nic nie zostało wdrożone.");

            return $lock;
        }

        $files = $private->existingDeployFiles();
        $manifest = $files === [] ? null : PrivatePackages::DEPLOY_MANIFEST;
        $installSource = $project->installSource();
        $installTarget = $installSource === null ? null : $project->installTarget();
        $preproduction = $this->preproduction($project, $files, $manifest, $installSource, $output);

        if ($preproduction !== ExitCode::SUCCESS) {
            return $preproduction;
        }

        if (! $this->productionWanted($project, $options->production)) {
            $output->line('==> Produkcja pominięta — zaktualizowano tylko przed-produkcję');

            return ExitCode::SUCCESS;
        }

        if ($files !== []) {
            $this->upload($project, $files, $output);
        }

        if ($installSource !== null) {
            $this->uploadInstallFile($project, $installSource, $output);
        }

        $exitCode = $this->runScript(
            "==> Uruchamiam wdrożenie na {$project->productionTarget()}",
            $this->script->remoteCommand($project),
            $this->script->production($project, $manifest, $installTarget),
            null,
            'Wdrożenie na serwerze zakończyło się błędem',
            $output,
        );

        if ($exitCode === ExitCode::SUCCESS) {
            $output->line('==> Gotowe');
        }

        return $exitCode;
    }

    /**
     * Przed-produkcja: manifest wdrożeniowy do jej katalogu (stary jest usuwany) i remote.sh w nim (`.env` bez zmian).
     *
     * @param  list<string>  $files  Pliki manifestu wdrożeniowego; pusta lista — paczki z `composer.lock`.
     * @param  string|null  $manifest  Nazwa manifestu dla remote.sh.
     * @param  string|null  $installSource  Plik instalacji projektu; null — bez pliku.
     *
     * @throws DeployException Gdy katalog nie jest klonem projektu albo manifestu nie da się skopiować.
     * @throws ProcessException Gdy basha nie da się uruchomić.
     * @throws PrivatePackagesException Gdy starego manifestu nie da się usunąć.
     */
    private function preproduction(ProjectConfig $project, array $files, ?string $manifest, ?string $installSource, Output $output): int
    {
        $directory = $project->preproductionPath;

        if (! is_file("{$directory}/artisan") || ! is_dir("{$directory}/.git")) {
            throw new DeployException("Przed-produkcja {$directory} nie jest projektem (brak artisan albo .git) — popraw " . ProjectConfig::PREPRODUCTION_PATH . '.');
        }

        (new PrivatePackages($directory))->removeDeploy();

        foreach ($files as $file) {
            if (! copy($file, "{$directory}/" . basename($file))) {
                throw new DeployException('Nie udało się skopiować ' . basename($file) . " do przed-produkcji {$directory}.");
            }
        }

        if ($installSource !== null) {
            $this->copyInstallFile($installSource, "{$directory}/{$project->installTarget()}");
        }

        return $this->runScript(
            "==> Aktualizuję przed-produkcję {$directory}",
            $this->script->localCommand(),
            $this->script->preproduction($project, $manifest, $installSource === null ? null : $project->installTarget()),
            $directory,
            'Aktualizacja przed-produkcji zakończyła się błędem — produkcja nie została wdrożona',
            $output,
        );
    }

    /**
     * Wysyła manifest wdrożeniowy do katalogu aplikacji na serwerze (scp).
     *
     * @param  list<string>  $files
     *
     * @throws DeployException Gdy wysyłka się nie powiodła.
     * @throws ProcessException Gdy scp nie da się uruchomić.
     */
    private function upload(ProjectConfig $project, array $files, Output $output): void
    {
        $output->line("==> Wysyłam manifest wdrożeniowy na {$project->productionTarget()}");

        if ($this->processes->run(['scp', '-q', ...$files, "{$project->productionTarget()}/"]) !== ExitCode::SUCCESS) {
            throw new DeployException("Nie udało się wysłać manifestu wdrożeniowego na {$project->productionTarget()}.");
        }
    }

    /**
     * Kopia pliku instalacji do przed-produkcji z prawami tylko dla właściciela (dane kont).
     *
     * @throws DeployException Gdy kopii nie da się zapisać.
     */
    private function copyInstallFile(string $source, string $target): void
    {
        $directory = dirname($target);

        if ((! is_dir($directory) && ! mkdir($directory, 0o775, true)) || ! copy($source, $target) || ! chmod($target, 0o600)) {
            throw new DeployException('Nie udało się skopiować pliku instalacji do ' . $target . '.');
        }
    }

    /**
     * Wysyła plik instalacji do katalogu aplikacji na serwerze (scp).
     *
     * @throws DeployException Gdy wysyłka się nie powiodła.
     * @throws ProcessException Gdy scp nie da się uruchomić.
     */
    private function uploadInstallFile(ProjectConfig $project, string $source, Output $output): void
    {
        $output->line("==> Wysyłam plik instalacji na {$project->productionTarget()}");

        if ($this->processes->run(['scp', '-q', $source, "{$project->productionTarget()}/{$project->installTarget()}"]) !== ExitCode::SUCCESS) {
            throw new DeployException("Nie udało się wysłać pliku instalacji na {$project->productionTarget()}.");
        }
    }

    private function productionWanted(ProjectConfig $project, ProductionMode $production): bool
    {
        return match ($production) {
            ProductionMode::Always => true,
            ProductionMode::Skip => false,
            ProductionMode::Ask => $this->confirmation->ask("Wdrożyć też na produkcję {$project->productionTarget()}? [t/N] "),
        };
    }

    /**
     * Uruchamia skrypt i zgłasza błąd z kodem wyjścia.
     *
     * @param  list<string>  $command  Polecenie, które czyta skrypt ze standardowego wejścia.
     * @param  string|null  $directory  Katalog roboczy; null — bieżący.
     *
     * @throws ProcessException Gdy procesu nie da się uruchomić.
     */
    private function runScript(string $title, array $command, string $script, ?string $directory, string $failure, Output $output): int
    {
        $output->line($title);
        $exitCode = $this->processes->run($command, $script, $directory);

        if ($exitCode !== ExitCode::SUCCESS) {
            $output->error("{$failure} (kod {$exitCode}).");
        }

        return $exitCode;
    }

    /**
     * `--with-push`: najpierw paczki z repozytoriów path (manifest przypina je do commitów z ich `origin`), potem projekt;
     * z `--dry-run` tylko wymienia paczki do wypchnięcia (projekt — w sprawdzeniu repozytorium).
     *
     * @throws DeployException|PrivatePackagesException Gdy `git push` projektu albo paczki się nie powiódł.
     */
    private function push(PrivatePackages $private, DeployOptions $options, Output $output): void
    {
        if ($options->dryRun) {
            $packages = $this->deployLock->unpushedPackages($private);

            if ($packages !== []) {
                $output->line('Do wypchnięcia (' . DeployOptions::WITH_PUSH . '): paczki ' . implode(', ', $packages) . '.');
            }

            return;
        }

        $output->line('==> Wypycham paczki');
        $output->line($this->deployLock->pushPackages($private, $output) === [] ? 'Nic do wypchnięcia.' : 'Paczki wypchnięte.');
        $output->line('==> Wypycham projekt');
        $output->line($this->repository->push($options->root) ? 'Wypchnięty.' : 'Nic do wypchnięcia.');
    }

    private function repositoryReady(DeployOptions $options, Output $output): bool
    {
        $output->line('==> Sprawdzam, czy projekt jest wypchnięty');
        $report = $this->repository->check($options->root);

        if ($report->unfetched) {
            $output->error('Uwaga: nie udało się pobrać stanu z origin — sprawdzam według ostatniego pobrania.');
        }

        if ($report->uncommitted) {
            $output->error('Uwaga: projekt ma niezacommitowane zmiany — nie trafią na serwer.');
        }

        if (! $report->unpushed) {
            return true;
        }

        if ($options->withPush && $options->dryRun) {
            $output->line('Do wypchnięcia (' . DeployOptions::WITH_PUSH . '): projekt.');

            return true;
        }

        $output->error('Niewypchnięte commity. Zrób git push (albo uruchom z ' . DeployOptions::WITH_PUSH . ') i uruchom ponownie.');

        return false;
    }
}
