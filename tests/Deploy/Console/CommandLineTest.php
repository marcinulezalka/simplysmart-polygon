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

namespace Simplysmart\Polygon\Tests\Deploy\Console;

use Simplysmart\Polygon\Deploy\Console\CommandLine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Argumenty wywołania: bez nazwy skryptu i tylko tekstowe.
 */
final class CommandLineTest extends TestCase
{
    #[Test]
    public function the_script_name_is_dropped(): void
    {
        $commandLine = CommandLine::fromArgv(['bin/deploy', 'shop', '--dry-run'], '/repo');

        $this->assertSame(['shop', '--dry-run'], $commandLine->arguments);
        $this->assertSame('/repo', $commandLine->workingDirectory);
    }

    #[Test]
    public function missing_or_non_text_arguments_are_ignored(): void
    {
        $this->assertSame([], CommandLine::fromArgv(null, '/repo')->arguments);
        $this->assertSame(['shop'], CommandLine::fromArgv(['bin/deploy', 1, 'shop'], '/repo')->arguments);
    }
}
