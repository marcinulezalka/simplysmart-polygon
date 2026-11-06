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

namespace Simplysmart\Polygon\Tests\Deploy\Process;

use Simplysmart\Polygon\Deploy\Process\SystemProcessRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Prawdziwe procesy (sh): kod wyjścia, standardowe wejście, katalog roboczy i przechwycone wyjście.
 */
final class SystemProcessRunnerTest extends TestCase
{
    #[Test]
    public function run_returns_the_exit_code_and_feeds_standard_input(): void
    {
        $runner = new SystemProcessRunner();

        $this->assertSame(3, $runner->run(['sh', '-c', 'exit 3'], ''));
        $this->assertSame(0, $runner->run(['sh', '-c', 'test "$(cat)" = skrypt'], 'skrypt'));
    }

    #[Test]
    public function capture_returns_trimmed_output_in_the_given_directory(): void
    {
        $runner = new SystemProcessRunner();
        $directory = (string) realpath(sys_get_temp_dir());

        $this->assertSame($directory, $runner->capture(['sh', '-c', 'pwd -P'], $directory));
        $this->assertSame('tekst', $runner->capture(['sh', '-c', "printf ' tekst \\n'"]));
    }

    #[Test]
    public function capture_returns_null_when_the_command_fails(): void
    {
        $this->assertNull((new SystemProcessRunner())->capture(['sh', '-c', 'echo x; exit 1']));
    }
}
