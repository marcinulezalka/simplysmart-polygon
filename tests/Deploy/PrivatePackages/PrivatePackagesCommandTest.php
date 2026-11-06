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
        array_map('unlink', glob("{$this->root}/*") ?: []);
        rmdir($this->root);
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
        $exitCode = (new PrivatePackagesCommand(new ComposerUpdate($this->processes)))->run($arguments, $this->root, $output->output);

        return [$exitCode, $output];
    }
}
