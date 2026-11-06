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
use Simplysmart\Polygon\Deploy\Deployment\DeployCommand;
use Simplysmart\Polygon\Deploy\Deployment\ProjectConfig;
use Simplysmart\Polygon\Deploy\Deployment\RemoteScript;
use Simplysmart\Polygon\Deploy\Deployment\RepositoryCheck;
use Simplysmart\Polygon\Tests\Deploy\Support\FakeConfirmation;
use Simplysmart\Polygon\Tests\Deploy\Support\FakeProcessRunner;
use Simplysmart\Polygon\Tests\Deploy\Support\OutputBuffer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Wdrożenie projektu z `infrastructure/deploy/vendors/<projekt>.env`: repozytorium, przed-produkcja, decyzja o produkcji i produkcja;
 * procesy i pytanie to atrapy, katalogi są tymczasowe.
 */
final class DeployCommandTest extends TestCase
{
    private string $root;

    private string $preproduction;

    private FakeProcessRunner $processes;

    private FakeConfirmation $confirmation;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/deploy-' . bin2hex(random_bytes(4));
        $this->root = $base;
        $vendors = "{$base}/" . ProjectConfig::DIRECTORY;
        $this->preproduction = "{$base}/pp";
        mkdir($vendors, recursive: true);
        mkdir("{$this->preproduction}/.git", recursive: true);
        file_put_contents("{$this->preproduction}/artisan", '');
        file_put_contents("{$vendors}/shop.env", "PREPRODUCTION_PATH={$this->preproduction}\nDEPLOY_SSH=shop@prod\nDEPLOY_PATH=~/shop.pl\nGITHUB_TOKEN='sekret'\n");
        $this->processes = new FakeProcessRunner(['rev-list' => '0']);
        $this->confirmation = new FakeConfirmation();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    #[Test]
    public function preproduction_runs_first_and_production_follows_a_yes_answer(): void
    {
        $this->confirmation->answer = true;

        [$exitCode] = $this->deploy(['shop']);

        $this->assertSame(ExitCode::SUCCESS, $exitCode);
        $this->assertSame([['bash', '-s'], ['ssh', 'shop@prod', 'bash -s']], $this->processes->commands());
        $this->assertSame($this->preproduction, $this->processes->runs[0]['directory']);
        $this->assertStringContainsString("DEPLOY_KEEP_ENV='1'", (string) $this->processes->runs[0]['input']);
        $this->assertStringNotContainsString('sekret', (string) $this->processes->runs[0]['input']);
        $this->assertStringContainsString("GITHUB_TOKEN='sekret'", (string) $this->processes->runs[1]['input']);
        $this->assertSame(['Wdrożyć też na produkcję shop@prod:~/shop.pl? [t/N] '], $this->confirmation->questions);
    }

    #[Test]
    public function a_no_answer_updates_only_preproduction(): void
    {
        [$exitCode, $stdout] = $this->deploy(['shop']);

        $this->assertSame(ExitCode::SUCCESS, $exitCode);
        $this->assertSame(['bash'], $this->processes->programs());
        $this->assertStringContainsString('Produkcja pominięta', $stdout);
    }

    #[Test]
    public function production_flags_skip_the_question(): void
    {
        $this->deploy(['shop', '--production']);
        $this->deploy(['shop', '--no-production']);

        $this->assertSame([], $this->confirmation->questions);
        $this->assertSame(['bash', 'ssh', 'bash'], $this->processes->programs());
    }

    #[Test]
    public function a_failed_preproduction_stops_before_production(): void
    {
        $this->processes->exitCodes = ['bash' => 3];

        [$exitCode, , $stderr] = $this->deploy(['shop', '--production']);

        $this->assertSame(3, $exitCode);
        $this->assertSame(['bash'], $this->processes->programs());
        $this->assertStringContainsString('produkcja nie została wdrożona (kod 3)', $stderr);
    }

    #[Test]
    public function a_failed_production_returns_the_script_exit_code(): void
    {
        $this->processes->exitCodes = ['ssh' => 5];

        [$exitCode, $stdout, $stderr] = $this->deploy(['shop', '--production']);

        $this->assertSame(5, $exitCode);
        $this->assertStringContainsString('Wdrożenie na serwerze zakończyło się błędem (kod 5)', $stderr);
        $this->assertStringNotContainsString('Gotowe', $stdout);
    }

    #[Test]
    public function unpushed_commits_stop_the_deploy_unless_pushed_first(): void
    {
        $this->processes->captures = ['rev-list' => '2'];

        [$exitCode, , $stderr] = $this->deploy(['shop', '--production']);

        $this->assertSame(ExitCode::FAILURE, $exitCode);
        $this->assertSame([], $this->processes->programs());
        $this->assertStringContainsString('Niewypchnięte commity', $stderr);

        $this->deploy(['shop', '--production', '--with-push']);

        $this->assertContains(['git', '-C', $this->root, 'push', '--quiet'], $this->processes->commands());
    }

    #[Test]
    public function dry_run_shows_the_plan_without_running_anything(): void
    {
        $this->processes->captures = ['rev-list' => '1'];

        [$exitCode, $stdout] = $this->deploy(['shop', '--dry-run', '--with-push']);

        $this->assertSame(ExitCode::SUCCESS, $exitCode);
        $this->assertSame([], $this->processes->programs());
        $this->assertStringContainsString('Do wypchnięcia (--with-push): projekt.', $stdout);
        $this->assertStringContainsString("Przed-produkcja: {$this->preproduction}", $stdout);
        $this->assertStringContainsString('Produkcja: shop@prod:~/shop.pl, PHP: php84', $stdout);
    }

    #[Test]
    public function a_missing_or_unknown_project_lists_the_available_ones(): void
    {
        [$missingExit, , $missing] = $this->deploy([]);
        [$unknownExit, , $unknown] = $this->deploy(['nope']);

        $this->assertSame([ExitCode::FAILURE, ExitCode::FAILURE], [$missingExit, $unknownExit]);
        $this->assertStringContainsString('Podaj projekt. Dostępne projekty: shop.', $missing);
        $this->assertStringContainsString('Brak projektu nope', $unknown);
    }

    #[Test]
    public function an_unknown_option_is_rejected(): void
    {
        [$exitCode, , $stderr] = $this->deploy(['shop', '--force']);

        $this->assertSame(ExitCode::FAILURE, $exitCode);
        $this->assertStringContainsString('Nieznana opcja: --force', $stderr);
    }

    #[Test]
    public function a_preproduction_directory_without_a_project_is_rejected(): void
    {
        unlink("{$this->preproduction}/artisan");

        [$exitCode, , $stderr] = $this->deploy(['shop', '--production']);

        $this->assertSame(ExitCode::FAILURE, $exitCode);
        $this->assertSame([], $this->processes->programs());
        $this->assertStringContainsString('nie jest projektem', $stderr);
    }

    /**
     * @param  list<string>  $arguments
     * @return array{0: int, 1: string, 2: string} Kod wyjścia, wyjście i błędy.
     */
    private function deploy(array $arguments): array
    {
        $buffer = new OutputBuffer();
        $command = new DeployCommand(
            $this->processes,
            new RepositoryCheck($this->processes),
            new RemoteScript("echo remote\n"),
            $this->confirmation,
        );

        $exitCode = $command->run(["--root={$this->root}", ...$arguments], '/nonexistent', $buffer->output);

        return [$exitCode, $buffer->stdout(), $buffer->stderr()];
    }
}
