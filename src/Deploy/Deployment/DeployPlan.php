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

/**
 * Opis wdrożenia pokazywany przy `--dry-run`: co i gdzie zostałoby uruchomione.
 */
final readonly class DeployPlan
{
    public function __construct(
        private ProjectConfig $project,
        private ProductionMode $production,
    ) {}

    /**
     * @return list<string> Linie planu.
     */
    public function lines(): array
    {
        return [
            "Projekt: {$this->project->name}.",
            "Przed-produkcja: {$this->project->preproductionPath}, PHP: {$this->project->preproductionPhp}; remote.sh lokalnie (git pull, composer install --no-dev, migracje, cache); .env bez zmian.",
            "Produkcja: {$this->project->productionTarget()}, PHP: {$this->project->php}; {$this->production->describe()}",
            'Środowisko produkcji: ' . RemoteScript::PRODUCTION_ENV . ' z serwera zostanie skopiowany jako .env (APP_KEY serwera zostaje, gdy plik go nie podaje); lokalny plik nie jest wysyłany.',
            'Tryb ' . DeployOptions::DRY_RUN . ': nic nie zostało uruchomione.',
        ];
    }
}
