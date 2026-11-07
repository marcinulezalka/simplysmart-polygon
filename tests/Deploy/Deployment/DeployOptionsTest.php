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

use Simplysmart\Polygon\Deploy\Deployment\DeployException;
use Simplysmart\Polygon\Deploy\Deployment\DeployOptions;
use Simplysmart\Polygon\Deploy\Deployment\ProductionMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Opcje bin/deploy z linii poleceń.
 */
final class DeployOptionsTest extends TestCase
{
    #[Test]
    public function defaults_ask_about_production_in_the_working_directory(): void
    {
        $options = DeployOptions::fromArguments(['shop'], '/repo');

        $this->assertSame(['shop', '/repo', ProductionMode::Ask, false, false, false], [$options->project, $options->root, $options->production, $options->dryRun, $options->withPush, $options->fresh]);
    }

    #[Test]
    public function options_are_read_in_any_order(): void
    {
        $options = DeployOptions::fromArguments(['--dry-run', 'shop', '--with-push', '--root=/other', '--no-production'], '/repo');

        $this->assertSame(['shop', '/other', ProductionMode::Skip, true, true], [$options->project, $options->root, $options->production, $options->dryRun, $options->withPush]);
        $this->assertSame(ProductionMode::Always, DeployOptions::fromArguments([DeployOptions::PRODUCTION], '/repo')->production);
        $this->assertTrue(DeployOptions::fromArguments(['shop', DeployOptions::FRESH], '/repo')->fresh);
        $this->assertStringContainsString(DeployOptions::FRESH, DeployOptions::USAGE);
    }

    #[Test]
    public function the_project_is_optional_here(): void
    {
        $this->assertNull(DeployOptions::fromArguments([], '/repo')->project);
    }

    #[Test]
    public function an_unknown_option_or_a_second_project_is_rejected(): void
    {
        foreach ([['--force'], ['shop', 'blog']] as $arguments) {
            try {
                DeployOptions::fromArguments($arguments, '/repo');
                $this->fail('Oczekiwano DeployException dla ' . implode(' ', $arguments));
            } catch (DeployException $exception) {
                $this->assertStringContainsString(DeployOptions::USAGE, $exception->getMessage());
            }
        }
    }
}
