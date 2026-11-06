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

namespace Simplysmart\Polygon\Deploy\Console;

/**
 * Wywołanie z linii poleceń: argumenty bez nazwy skryptu i katalog, z którego uruchomiono polecenie.
 */
final readonly class CommandLine
{
    /** Opcja wskazująca katalog projektu zamiast katalogu roboczego (wspólna dla wszystkich poleceń). */
    public const string ROOT_OPTION = '--root=';

    /** Opcja podglądu bez zmian (wspólna dla wszystkich poleceń). */
    public const string DRY_RUN_OPTION = '--dry-run';

    private const string CURRENT_DIRECTORY = '.';

    /**
     * @param  list<string>  $arguments  Argumenty bez nazwy skryptu.
     * @param  string  $workingDirectory  Katalog roboczy procesu.
     */
    public function __construct(
        public array $arguments,
        public string $workingDirectory,
    ) {}

    /**
     * Wywołanie bieżącego procesu (`$_SERVER['argv']`, getcwd()).
     */
    public static function current(): self
    {
        return self::fromArgv($_SERVER['argv'] ?? [], getcwd() ?: self::CURRENT_DIRECTORY);
    }

    /**
     * @param  mixed  $argv  Tablica argumentów z nazwą skryptu na początku (jak `$_SERVER['argv']`).
     */
    public static function fromArgv(mixed $argv, string $workingDirectory): self
    {
        $arguments = is_array($argv) ? array_slice($argv, 1) : [];

        return new self(array_values(array_filter($arguments, is_string(...))), $workingDirectory);
    }
}
