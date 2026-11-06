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

/**
 * Repozytorium git paczki z katalogu (repozytorium typu path): adres zdalny `origin` i czy ma commity
 * niewypchnięte do gałęzi śledzonej.
 */
interface RepositorySource
{
    /**
     * @param  string  $directory  Katalog paczki.
     * @return string|null Adres `origin` albo null, gdy katalog nie jest repozytorium z `origin`.
     */
    public function originUrl(string $directory): ?string;

    /**
     * @param  string  $directory  Katalog paczki.
     */
    public function hasUnpushedCommits(string $directory): bool;
}
