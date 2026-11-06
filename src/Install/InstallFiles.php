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

use Simplysmart\Polygon\PolygonConfig;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Simplysmart\Polygon\PolygonServiceProvider;

/**
 * Pliki instalacji w kolejności stosowania: domyślny plik paczki (`database/install/polygon.json`), pliki paczek stref
 * dodane przez add() (w kolejności rejestracji providerów), potem pliki projektu z `POLYGON_INSTALL_FILES`
 * (`polygon.install.files`, ścieżki względem katalogu projektu) — tak paczka strefy i projekt dołączają własne strefy,
 * role i konta bez zmian w polygon. Singleton (Providers\PermissionsServiceProvider).
 */
final class InstallFiles
{
    public const string PACKAGE_FILE = PolygonServiceProvider::ROOT . '/database/install/polygon.json';

    /**
     * @var list<string>
     */
    private array $packageFiles = [];

    public function __construct(
        private readonly Repository $config,
        private readonly Application $app,
    ) {}

    /**
     * Plik instalacji paczki strefy (ścieżka absolutna), dodawany przez provider paczki
     * (`callAfterResolving(InstallFiles::class, ...)` w register()).
     */
    public function add(string $path): void
    {
        if (! in_array($path, $this->packageFiles, true)) {
            $this->packageFiles[] = $path;
        }
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        $files = [self::PACKAGE_FILE, ...$this->packageFiles];

        foreach ($this->config->array(PolygonConfig::INSTALL_FILES, []) as $path) {
            if (is_string($path) && $path !== '') {
                $files[] = str_starts_with($path, '/') ? $path : $this->app->basePath($path);
            }
        }

        return $files;
    }
}
