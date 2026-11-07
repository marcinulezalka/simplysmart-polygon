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

use Simplysmart\Polygon\Deploy\Console\CommandLine;

/**
 * Opcje wywołania vendor/bin/deploy z linii poleceń.
 */
final readonly class DeployOptions
{
    public const string DRY_RUN = CommandLine::DRY_RUN_OPTION;

    public const string WITH_PUSH = '--with-push';

    public const string PRODUCTION = '--production';

    public const string NO_PRODUCTION = '--no-production';

    /** Baza od nowa (`migrate:fresh` + `db:seed`) zamiast migracji — usuwa wszystkie dane; każde środowisko pyta osobno. */
    public const string FRESH = '--fresh';

    public const string ROOT = CommandLine::ROOT_OPTION;

    public const string USAGE = 'Użycie: vendor/bin/deploy <projekt> [' . self::PRODUCTION . '|' . self::NO_PRODUCTION . '] ['
        . self::DRY_RUN . '] [' . self::WITH_PUSH . '] [' . self::FRESH . '] [' . self::ROOT . '<katalog>]';

    private const string OPTION_PREFIX = '-';

    /**
     * @param  string|null  $project  Nazwa projektu z `infrastructure/deploy/vendors/`; null — nie podano.
     * @param  string  $root  Katalog repozytorium projektu (sprawdzanego i wypychanego przed wdrożeniem).
     * @param  bool  $fresh  Baza od nowa w każdym wdrażanym środowisku (po potwierdzeniu dla każdego z nich).
     */
    public function __construct(
        public ?string $project,
        public string $root,
        public ProductionMode $production = ProductionMode::Ask,
        public bool $dryRun = false,
        public bool $withPush = false,
        public bool $fresh = false,
    ) {}

    /**
     * @param  list<string>  $arguments  Argumenty bez nazwy skryptu.
     * @param  string  $defaultRoot  Katalog repozytorium, gdy nie podano --root.
     *
     * @throws DeployException Gdy podano nieznaną opcję albo drugą nazwę projektu.
     */
    public static function fromArguments(array $arguments, string $defaultRoot): self
    {
        $project = null;
        $root = $defaultRoot;
        $production = ProductionMode::Ask;
        $dryRun = false;
        $withPush = false;
        $fresh = false;

        foreach ($arguments as $argument) {
            match (true) {
                $argument === self::PRODUCTION => $production = ProductionMode::Always,
                $argument === self::NO_PRODUCTION => $production = ProductionMode::Skip,
                $argument === self::DRY_RUN => $dryRun = true,
                $argument === self::WITH_PUSH => $withPush = true,
                $argument === self::FRESH => $fresh = true,
                str_starts_with($argument, self::ROOT) => $root = substr($argument, strlen(self::ROOT)),
                $project === null && ! str_starts_with($argument, self::OPTION_PREFIX) => $project = $argument,
                default => throw new DeployException("Nieznana opcja: {$argument}\n" . self::USAGE),
            };
        }

        return new self($project, $root, $production, $dryRun, $withPush, $fresh);
    }
}
