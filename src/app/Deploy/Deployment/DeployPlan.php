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

use Simplysmart\Polygon\Deploy\PrivatePackages\PrivatePackages;

/**
 * Opis wdrożenia pokazywany przy `--dry-run`: co i gdzie zostałoby uruchomione.
 */
final readonly class DeployPlan
{
    public function __construct(
        private ProjectConfig $project,
        private ProductionMode $production,
        private bool $fresh = false,
    ) {}

    /**
     * @return list<string> Linie planu.
     */
    public function lines(): array
    {
        return [
            "Projekt: {$this->project->name}.",
            'Manifest wdrożeniowy: świeży ' . PrivatePackages::DEPLOY_MANIFEST . ' i ' . PrivatePackages::DEPLOY_LOCK . ' (paczki z repozytoriów path z ich repozytoriów git), kopiowany do przed-produkcji i wysyłany na produkcję (scp); bez paczek z repozytoriów path — ' . PrivatePackages::PUBLIC_LOCK . '.',
            "Plik instalacji: {$this->project->installFile()} (jeśli jest) kopiowany jako {$this->project->installTarget()} do obu środowisk; remote.sh po migracjach uruchamia polygon:install (pliki paczek, POLYGON_INSTALL_FILES i ten plik).",
            "Przed-produkcja: {$this->project->preproductionPath}, PHP: {$this->project->preproductionPhp}; remote.sh lokalnie (git pull, composer install --no-dev, migracje, polygon:install, cache); .env bez zmian.",
            "Produkcja: {$this->project->productionTarget()}, PHP: {$this->project->php}; {$this->production->describe()}",
            'Środowisko produkcji: ' . RemoteScript::PRODUCTION_ENV . ' z serwera zostanie skopiowany jako .env (APP_KEY serwera zostaje, gdy plik go nie podaje); wzorzec ' . "{$this->project->directory}/{$this->project->name}" . RemoteScript::PRODUCTION_ENV . ' przenieś na serwer ręcznie — deploy go nie wysyła.',
            'Kolejka: deploy nie uruchamia workera; z QUEUE_CONNECTION=database maile logowania wymagają workera z crona (docs/deploy.md, „Kolejka i worker”).',
            ...($this->fresh ? ['Baza od nowa (' . DeployOptions::FRESH . '): w każdym wdrażanym środowisku migrate:fresh (USUWA WSZYSTKIE DANE) i db:seed z plikami instalacji zamiast migracji — po osobnym potwierdzeniu dla przed-produkcji i produkcji.'] : []),
            'Tryb ' . DeployOptions::DRY_RUN . ': nic nie zostało uruchomione.',
        ];
    }
}
