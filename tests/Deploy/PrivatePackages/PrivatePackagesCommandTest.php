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

namespace Simplysmart\Polygon\Tests\Deploy\PrivatePackages;

use PHPUnit\Framework\TestCase;
use Simplysmart\Polygon\Deploy\Console\ExitCode;
use Simplysmart\Polygon\Deploy\PrivatePackages\ComposerUpdate;
use Simplysmart\Polygon\Deploy\PrivatePackages\DeployLock;
use Simplysmart\Polygon\Deploy\PrivatePackages\GitRepositorySource;
use Simplysmart\Polygon\Deploy\PrivatePackages\PrivatePackagesCommand;
use Simplysmart\Polygon\Tests\Deploy\Support\FakeProcessRunner;
use Simplysmart\Polygon\Tests\Deploy\Support\OutputBuffer;

/**
 * Instalacja paczek prywatnych (vendor/bin/composer-private) — zawsze w katalogu tymczasowym, z composerem
 * zastąpionym zapisem wywołań procesów.
 */
final class PrivatePackagesCommandTest extends TestCase
{
    private string $root;

    private FakeProcessRunner $processes;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/composer-private-' . bin2hex(random_bytes(4));
        mkdir($this->root);
        file_put_contents("{$this->root}/composer.json", <<<'JSON'
            {
                "name": "acme/app",
                "require": {
                    "php": "^8.4"
                },
                "extra": {
                    "laravel": {
                        "dont-discover": []
                    },
                    "empty": {}
                }
            }
            JSON);
        file_put_contents("{$this->root}/composer.lock", '{"packages": []}');
        $this->processes = new FakeProcessRunner();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_without_a_list_nothing_is_installed(): void
    {
        [$exitCode, $output] = $this->runCommand([]);

        $this->assertSame(ExitCode::SUCCESS, $exitCode);
        $this->assertStringContainsString('nic do zainstalowania', $output->stdout());
        $this->assertFileDoesNotExist("{$this->root}/composer.private.json");
        $this->assertSame([], $this->processes->runs);
    }

    public function test_private_packages_are_merged_and_composer_runs_with_the_private_manifest(): void
    {
        $this->writeList('{"require": {"acme/secret": "@dev"}}');

        [$exitCode, $output] = $this->runCommand([]);

        $this->assertSame(ExitCode::SUCCESS, $exitCode);
        $this->assertSame("Paczki prywatne: acme/secret.\n", $output->stdout());
        $manifest = (string) file_get_contents("{$this->root}/composer.private.json");
        $this->assertStringContainsString('"acme/secret": "@dev"', $manifest);
        $this->assertStringContainsString('"empty": {}', $manifest);
        $this->assertStringContainsString('"dont-discover": []', $manifest);
        $this->assertStringNotContainsString('acme/secret', (string) file_get_contents("{$this->root}/composer.json"));
        $this->assertFileEquals("{$this->root}/composer.lock", "{$this->root}/composer.private.lock");

        $run = $this->processes->runs[0];
        $this->assertSame(['env', 'COMPOSER=composer.private.json'], array_slice($run['command'], 0, 2));
        $this->assertSame(['update', 'acme/secret', '--no-interaction'], array_slice($run['command'], -3));
        $this->assertSame($this->root, $run['directory']);
    }

    public function test_an_existing_private_lock_is_kept(): void
    {
        $this->writeList('{"require": {"acme/secret": "@dev"}}');
        file_put_contents("{$this->root}/composer.private.lock", '{"private": true}');

        $this->runCommand([]);

        $this->assertSame('{"private": true}', file_get_contents("{$this->root}/composer.private.lock"));
    }

    public function test_dry_run_writes_nothing_and_does_not_run_composer(): void
    {
        $this->writeList('{"require": {"acme/secret": "@dev"}}');

        [$exitCode, $output] = $this->runCommand(['--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $exitCode);
        $this->assertStringContainsString('Tryb --dry-run', $output->stdout());
        $this->assertFileDoesNotExist("{$this->root}/composer.private.json");
        $this->assertSame([], $this->processes->runs);
    }

    public function test_an_invalid_list_fails(): void
    {
        foreach (['{"require": ', '{"require": {"Bad Name": "@dev"}}', '{"require": {"acme/secret": 1}}', '{"packages": []}'] as $list) {
            $this->writeList($list);

            [$exitCode, $output] = $this->runCommand([]);

            $this->assertSame(ExitCode::FAILURE, $exitCode, $list);
            $this->assertNotSame('', $output->stderr(), $list);
        }

        $this->assertSame([], $this->processes->runs);
    }

    public function test_composer_exit_code_is_the_exit_code(): void
    {
        $this->writeList('{"require": {"acme/secret": "@dev"}}');
        $this->processes = new FakeProcessRunner(exitCodes: ['env' => 2]);

        [$exitCode] = $this->runCommand([]);

        $this->assertSame(2, $exitCode);
    }

    public function test_an_unknown_option_shows_the_usage(): void
    {
        [$exitCode, $output] = $this->runCommand(['--force']);

        $this->assertSame(ExitCode::FAILURE, $exitCode);
        $this->assertStringContainsString('Użycie: composer-private', $output->stderr());
    }

    public function test_deploy_mode_builds_a_manifest_with_git_repositories_instead_of_path_ones(): void
    {
        $this->withPathPackages();
        $this->writeList('{"require": {"acme/secret": "@dev"}}');
        $this->processes->captures = ['remote' => 'https://example.test/acme/secret.git', 'rev-list' => '0'];

        [$exitCode, $output] = $this->runCommand(['--deploy']);

        $this->assertSame(ExitCode::SUCCESS, $exitCode, $output->stderr());
        $this->assertSame("Paczki z repozytoriów git: acme/core, acme/secret.\n", $output->stdout());
        $manifest = json_decode((string) file_get_contents("{$this->root}/composer.deploy.json"), true);
        $this->assertIsArray($manifest);
        $this->assertSame(['php' => '^8.4', 'acme/core' => '@dev', 'acme/secret' => '@dev'], $manifest['require']);
        $this->assertSame([
            ['type' => 'composer', 'url' => 'https://repo.example.test'],
            ['type' => 'vcs', 'url' => 'https://example.test/acme/secret.git'],
            ['type' => 'vcs', 'url' => 'https://example.test/acme/secret.git'],
        ], $manifest['repositories']);
        $this->assertFileEquals("{$this->root}/composer.lock", "{$this->root}/composer.deploy.lock");
        $this->assertStringNotContainsString('acme/secret', (string) file_get_contents("{$this->root}/composer.json"));

        $run = $this->processes->runs[0];
        $this->assertSame(['env', 'COMPOSER=composer.deploy.json'], array_slice($run['command'], 0, 2));
        $this->assertSame(['update', 'acme/core', 'acme/secret', '--no-install', '--no-scripts', '--no-interaction'], array_slice($run['command'], -6));
    }

    public function test_deploy_mode_without_path_packages_writes_no_manifest(): void
    {
        file_put_contents("{$this->root}/composer.deploy.json", '{}');

        [$exitCode, $output] = $this->runCommand(['--deploy']);

        $this->assertSame(ExitCode::SUCCESS, $exitCode);
        $this->assertStringContainsString('manifest wdrożeniowy niepotrzebny', $output->stdout());
        $this->assertFileDoesNotExist("{$this->root}/composer.deploy.json");
        $this->assertSame([], $this->processes->runs);
    }

    public function test_deploy_mode_fails_for_a_package_without_origin(): void
    {
        $this->withPathPackages();
        $this->processes->captures = ['remote' => null];

        [$exitCode, $output] = $this->runCommand(['--deploy']);

        $this->assertSame(ExitCode::FAILURE, $exitCode);
        $this->assertStringContainsString('nie ma repozytorium git z origin', $output->stderr());
        $this->assertFileDoesNotExist("{$this->root}/composer.deploy.json");
    }

    public function test_deploy_mode_warns_about_unpushed_packages_and_dry_run_writes_nothing(): void
    {
        $this->withPathPackages();
        $this->processes->captures = ['remote' => 'https://example.test/acme/core.git', 'rev-list' => '2'];

        [$exitCode, $output] = $this->runCommand(['--deploy', '--dry-run']);

        $this->assertSame(ExitCode::SUCCESS, $exitCode);
        $this->assertStringContainsString('acme/core ma niewypchnięte commity', $output->stderr());
        $this->assertStringContainsString('Tryb --dry-run', $output->stdout());
        $this->assertFileDoesNotExist("{$this->root}/composer.deploy.json");
        $this->assertSame([], $this->processes->runs);
    }

    public function test_a_failed_deploy_lock_leaves_no_manifest(): void
    {
        $this->withPathPackages();
        $this->processes->captures = ['remote' => 'https://example.test/acme/core.git'];
        $this->processes->exitCodes = ['env' => 2];

        [$exitCode] = $this->runCommand(['--deploy']);

        $this->assertSame(2, $exitCode);
        $this->assertFileDoesNotExist("{$this->root}/composer.deploy.json");
        $this->assertFileDoesNotExist("{$this->root}/composer.deploy.lock");
    }

    /**
     * Projekt z repozytorium path `packages/*\/*`: wymagana paczka acme/core, paczka prywatna acme/secret
     * i niewymagana acme/unused.
     */
    private function withPathPackages(): void
    {
        file_put_contents("{$this->root}/composer.json", <<<'JSON'
            {
                "name": "acme/app",
                "require": {"php": "^8.4", "acme/core": "@dev"},
                "repositories": [
                    {"type": "path", "url": "packages/*/*", "options": {"symlink": true}},
                    {"type": "composer", "url": "https://repo.example.test"}
                ]
            }
            JSON);

        foreach (['core', 'secret', 'unused'] as $package) {
            mkdir("{$this->root}/packages/acme/{$package}", recursive: true);
            file_put_contents("{$this->root}/packages/acme/{$package}/composer.json", "{\"name\": \"acme/{$package}\"}");
        }
    }

    private function writeList(string $contents): void
    {
        file_put_contents("{$this->root}/private-packages.json", $contents);
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{int, OutputBuffer}
     */
    private function runCommand(array $arguments): array
    {
        $output = new OutputBuffer();
        $composer = new ComposerUpdate($this->processes);
        $command = new PrivatePackagesCommand($composer, new DeployLock($composer, new GitRepositorySource($this->processes)));
        $exitCode = $command->run($arguments, $this->root, $output->output);

        return [$exitCode, $output];
    }
}
