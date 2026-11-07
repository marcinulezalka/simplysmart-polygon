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
use Simplysmart\Polygon\Deploy\Process\ProcessRunner;

/**
 * Repozytorium paczki przez `git` w osobnym procesie: `git remote get-url origin`, liczba commitów `HEAD`, których nie
 * ma w żadnej gałęzi `origin` (`--not --remotes=origin`, więc działa też bez gałęzi śledzonej), i
 * `git push --set-upstream origin HEAD`.
 */
final readonly class GitRepositorySource implements RepositorySource
{
    private const string GIT = 'git';

    private const string REMOTE = 'origin';

    public function __construct(
        private ProcessRunner $processes,
    ) {}

    public function originUrl(string $directory): ?string
    {
        $url = $this->git($directory, ['remote', 'get-url', self::REMOTE]);

        return $url === null || $url === '' ? null : $url;
    }

    public function hasUnpushedCommits(string $directory): bool
    {
        $count = $this->git($directory, ['rev-list', '--count', 'HEAD', '--not', '--remotes=' . self::REMOTE]);

        return $count !== null && ctype_digit($count) && (int) $count > 0;
    }

    public function push(string $directory): bool
    {
        return $this->processes->run([self::GIT, '-C', $directory, 'push', '--quiet', '--set-upstream', self::REMOTE, 'HEAD']) === ExitCode::SUCCESS;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(string $directory, array $arguments): ?string
    {
        return $this->processes->capture([self::GIT, '-C', $directory, ...$arguments]);
    }
}
