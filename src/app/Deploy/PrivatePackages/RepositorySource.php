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
 * Repozytorium git paczki z katalogu (repozytorium typu path): adres zdalny `origin`, czy ma commity, których nie ma
 * w `origin`, i ich wypchnięcie (`--with-push` wdrożenia).
 */
interface RepositorySource
{
    /**
     * @param  string  $directory  Katalog paczki.
     * @return string|null Adres `origin` albo null, gdy katalog nie jest repozytorium z `origin`.
     */
    public function originUrl(string $directory): ?string;

    /**
     * Czy bieżąca gałąź ma commity, których nie ma w żadnej gałęzi `origin` (także bez gałęzi śledzonej).
     *
     * @param  string  $directory  Katalog paczki.
     */
    public function hasUnpushedCommits(string $directory): bool;

    /**
     * Wypycha bieżącą gałąź do `origin` pod tą samą nazwą i ustawia ją jako gałąź śledzoną.
     *
     * @param  string  $directory  Katalog paczki.
     * @return bool Czy `git push` się powiódł.
     */
    public function push(string $directory): bool;
}
