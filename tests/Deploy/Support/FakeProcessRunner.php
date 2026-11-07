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

namespace Simplysmart\Polygon\Tests\Deploy\Support;

use Simplysmart\Polygon\Deploy\Process\ProcessRunner;

/**
 * Atrapa procesów: zapisuje wywołania `run`, a `capture` odpowiada według podpolecenia git
 * (`fetch`, `rev-list`, `status`) — najpierw dla katalogu repozytorium (`<katalog> <podpolecenie>`), potem dla każdego.
 */
final class FakeProcessRunner implements ProcessRunner
{
    /**
     * @var list<array{command: list<string>, input: string|null, directory: string|null}>
     */
    public array $runs = [];

    /**
     * @param  array<string, string|null>  $captures  Wyjście `capture` dla podpolecenia git (`rev-list`) albo podpolecenia
     *                                               w katalogu (`/repo rev-list`); brak klucza — pusty tekst.
     * @param  array<string, int>  $exitCodes  Kod wyjścia `run` dla programu (`bash`, `ssh`, `git`); brak klucza — 0.
     */
    public function __construct(
        public array $captures = [],
        public array $exitCodes = [],
    ) {}

    public function run(array $command, ?string $input = null, ?string $directory = null): int
    {
        $this->runs[] = ['command' => $command, 'input' => $input, 'directory' => $directory];

        return $this->exitCodes[$command[0]] ?? 0;
    }

    public function capture(array $command, ?string $directory = null): ?string
    {
        $subcommand = $command[3] ?? '';
        $inDirectory = ($command[2] ?? '') . " {$subcommand}";

        if (array_key_exists($inDirectory, $this->captures)) {
            return $this->captures[$inDirectory];
        }

        return array_key_exists($subcommand, $this->captures) ? $this->captures[$subcommand] : '';
    }

    /**
     * Polecenia uruchomione przez `run`, w kolejności.
     *
     * @return list<list<string>>
     */
    public function commands(): array
    {
        return array_map(fn(array $run): array => $run['command'], $this->runs);
    }

    /**
     * Programy uruchomione przez `run`, w kolejności.
     *
     * @return list<string>
     */
    public function programs(): array
    {
        return array_map(fn(array $command): string => $command[0], $this->commands());
    }
}
