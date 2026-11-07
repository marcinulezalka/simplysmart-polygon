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

use Simplysmart\Polygon\Deploy\Console\ExitCode;
use Simplysmart\Polygon\Deploy\Process\ProcessRunner;

/**
 * Sprawdza, czy repozytorium projektu jest wypchnięte, zanim przed-produkcja i serwer pobiorą kod z `origin`
 * (`git pull`), i na życzenie (`--with-push`) je wypycha.
 */
final readonly class RepositoryCheck
{
    private const string GIT = 'git';

    private const string REMOTE = 'origin';

    private const string QUIET = '--quiet';

    public function __construct(
        private ProcessRunner $processes,
    ) {}

    /**
     * Pobiera stan `origin` i zgłasza niewypchnięte commity, niezacommitowane zmiany i nieudane pobranie.
     *
     * @param  string  $root  Katalog repozytorium projektu.
     */
    public function check(string $root): RepositoryReport
    {
        // Najpierw fetch: liczba niewypchniętych commitów zależy od świeżego stanu gałęzi śledzonej.
        $unfetched = $this->git($root, 'fetch', self::QUIET, self::REMOTE) === null;

        return new RepositoryReport(
            unpushed: $this->hasUnpushedCommits($root),
            uncommitted: $this->git($root, 'status', '--porcelain') !== '',
            unfetched: $unfetched,
        );
    }

    /**
     * Wypycha projekt (`git push --set-upstream origin HEAD`: bieżąca gałąź pod tą samą nazwą, także bez gałęzi
     * śledzonej), gdy ma commity nieobecne w `origin`.
     *
     * @param  string  $root  Katalog repozytorium projektu.
     * @return bool Czy coś zostało wypchnięte.
     *
     * @throws DeployException Gdy `git push` się nie powiódł.
     */
    public function push(string $root): bool
    {
        if (! $this->hasUnpushedCommits($root)) {
            return false;
        }

        if ($this->processes->run($this->command($root, 'push', self::QUIET, '--set-upstream', self::REMOTE, 'HEAD')) !== ExitCode::SUCCESS) {
            throw new DeployException('Nie udało się wypchnąć projektu (git push). Sprawdź dostęp do ' . self::REMOTE . ' i uruchom ponownie.');
        }

        return true;
    }

    /**
     * Czy bieżąca gałąź ma commity, których nie ma w żadnej gałęzi `origin` (także bez gałęzi śledzonej).
     */
    private function hasUnpushedCommits(string $root): bool
    {
        $count = $this->git($root, 'rev-list', '--count', 'HEAD', '--not', '--remotes=' . self::REMOTE);

        return $count !== null && ctype_digit($count) && (int) $count > 0;
    }

    /**
     * Wyjście polecenia git w repozytorium albo null, gdy się nie powiodło.
     */
    private function git(string $root, string ...$arguments): ?string
    {
        return $this->processes->capture($this->command($root, ...$arguments));
    }

    /**
     * @return list<string>
     */
    private function command(string $root, string ...$arguments): array
    {
        return [self::GIT, '-C', $root, ...array_values($arguments)];
    }
}
