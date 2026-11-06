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

namespace Simplysmart\Polygon\Tests\Deploy\Deployment;

use Simplysmart\Polygon\Deploy\Console\ExitCode;
use Simplysmart\Polygon\Deploy\Deployment\ProjectConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Punkt wejścia bin/deploy w katalogu bez konfiguracji projektów wskazuje wzór.
 */
final class EntryPointTest extends TestCase
{
    private const string BIN = __DIR__ . '/../../../bin/deploy';

    #[Test]
    public function without_a_project_it_points_to_the_example(): void
    {
        exec(sprintf('cd %s && %s %s --no-production 2>&1', escapeshellarg(sys_get_temp_dir()), PHP_BINARY, escapeshellarg((string) realpath(self::BIN))), $output, $exitCode);

        $this->assertSame(ExitCode::FAILURE, $exitCode);
        $this->assertStringContainsString('Podaj projekt.', implode("\n", $output));
        $this->assertStringContainsString(ProjectConfig::example(), implode("\n", $output));
    }
}
