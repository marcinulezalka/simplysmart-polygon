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
use Simplysmart\Polygon\Deploy\Deployment\RepositoryCheck;
use Simplysmart\Polygon\Deploy\Deployment\RepositoryReport;
use Simplysmart\Polygon\Tests\Deploy\Support\FakeProcessRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stan repozytorium projektu przed wdrożeniem i wypychanie (`--with-push`); git to atrapa.
 */
final class RepositoryCheckTest extends TestCase
{
    #[Test]
    public function a_clean_pushed_repository_is_ready(): void
    {
        $this->assertEquals(new RepositoryReport(), (new RepositoryCheck(new FakeProcessRunner(['rev-list' => '0'])))->check('/repo'));
    }

    #[Test]
    public function problems_are_reported(): void
    {
        $processes = new FakeProcessRunner(['fetch' => null, 'rev-list' => '3', 'status' => ' M app.php']);

        $this->assertEquals(new RepositoryReport(unpushed: true, uncommitted: true, unfetched: true), (new RepositoryCheck($processes))->check('/repo'));
    }

    #[Test]
    public function without_a_tracked_branch_nothing_counts_as_unpushed(): void
    {
        $processes = new FakeProcessRunner(['rev-list' => null]);

        $this->assertFalse((new RepositoryCheck($processes))->check('/repo')->unpushed);
        $this->assertFalse((new RepositoryCheck($processes))->push('/repo'));
        $this->assertSame([], $processes->commands());
    }

    #[Test]
    public function push_runs_only_with_unpushed_commits_and_reports_failures(): void
    {
        $processes = new FakeProcessRunner(['rev-list' => '1']);

        $this->assertTrue((new RepositoryCheck($processes))->push('/repo'));
        $this->assertSame([['git', '-C', '/repo', 'push', '--quiet', '--set-upstream', 'origin', 'HEAD']], $processes->commands());

        $processes->exitCodes = ['git' => 1];
        $this->expectException(DeployException::class);

        (new RepositoryCheck($processes))->push('/repo');
    }
}
