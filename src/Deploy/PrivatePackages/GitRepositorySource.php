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

use Simplysmart\Polygon\Deploy\Process\ProcessRunner;

/**
 * Odczyt repozytorium paczki przez `git` w osobnym procesie: `git remote get-url origin` i liczba commitów
 * przed gałęzią śledzoną (`@{u}..HEAD`; bez gałęzi śledzonej — brak niewypchniętych).
 */
final readonly class GitRepositorySource implements RepositorySource
{
    private const string GIT = 'git';

    public function __construct(
        private ProcessRunner $processes,
    ) {}

    public function originUrl(string $directory): ?string
    {
        $url = $this->git($directory, ['remote', 'get-url', 'origin']);

        return $url === null || $url === '' ? null : $url;
    }

    public function hasUnpushedCommits(string $directory): bool
    {
        $count = $this->git($directory, ['rev-list', '--count', '@{u}..HEAD']);

        return $count !== null && ctype_digit($count) && (int) $count > 0;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(string $directory, array $arguments): ?string
    {
        return $this->processes->capture([self::GIT, '-C', $directory, ...$arguments]);
    }
}
