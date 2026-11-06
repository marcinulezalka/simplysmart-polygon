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

namespace Simplysmart\Polygon\Deploy\Release;

use Simplysmart\Polygon\Deploy\Console\ExitCode;
use Simplysmart\Polygon\Deploy\Console\Output;

/**
 * Polecenie `vendor/bin/version-release`: zamienia argumenty na wydanie (albo jego podgląd przy --dry-run)
 * i wynik na komunikat.
 */
final readonly class VersionReleaseCommand
{
    private const string DRY_RUN_NOTICE = 'Tryb --dry-run: pliki nie zostały zmienione.';

    public function __construct(
        private VersionRelease $release,
    ) {}

    /**
     * @param  list<string>  $arguments  Argumenty bez nazwy skryptu.
     * @param  string  $workingDirectory  Domyślny katalog celu wydania.
     * @return int Kod wyjścia: ExitCode::SUCCESS — wydanie, podgląd lub brak wpisów, ExitCode::FAILURE — błąd.
     */
    public function run(array $arguments, string $workingDirectory, Output $output): int
    {
        try {
            $options = ReleaseOptions::fromArguments($arguments, $workingDirectory);
            $files = new ReleaseFiles($options->root);
            $plan = $options->dryRun
                ? $this->release->plan($files, $options->majorConfirmed)
                : $this->release->release($files, $options->majorConfirmed);
        } catch (ReleaseException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::FAILURE;
        }

        if ($plan === null) {
            $output->line('Sekcja ' . Changelog::UNRELEASED_TITLE . ' nie ma wpisów — nic do wydania.');

            return ExitCode::SUCCESS;
        }

        $summary = "Wersja {$plan->from} → {$plan->to} ({$plan->bump->value}).";
        $output->line($options->dryRun ? "{$summary} " . self::DRY_RUN_NOTICE : $summary);

        return ExitCode::SUCCESS;
    }
}
