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

use Simplysmart\Polygon\Deploy\Deployment\DeployConfigException;
use Simplysmart\Polygon\Deploy\Deployment\MissingConfigException;
use Simplysmart\Polygon\Deploy\Deployment\ProjectConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Konfiguracja projektu z `infrastructure/deploy/vendors/<projekt>.env`: wartości domyślne, wymagane klucze i lista projektów.
 */
final class ProjectConfigTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/project-config-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->directory));
    }

    #[Test]
    public function optional_values_fall_back_to_defaults(): void
    {
        $this->write('shop', "PREPRODUCTION_PATH=/var/www/pp-shop/\nDEPLOY_SSH=shop@prod\nDEPLOY_PATH=~/shop.pl\n");

        $config = ProjectConfig::load($this->directory, 'shop');

        $this->assertSame('/var/www/pp-shop', $config->preproductionPath);
        $this->assertSame(ProjectConfig::DEFAULT_PREPRODUCTION_PHP, $config->preproductionPhp);
        $this->assertSame(ProjectConfig::DEFAULT_PRODUCTION_PHP, $config->php);
        $this->assertNull($config->preproductionComposer);
        $this->assertNull($config->remoteComposer);
        $this->assertNull($config->githubToken);
        $this->assertSame('shop@prod:~/shop.pl', $config->productionTarget());
    }

    #[Test]
    public function given_values_are_kept(): void
    {
        $this->write('shop', "PREPRODUCTION_PATH=/pp\nPREPRODUCTION_PHP=php85\nPREPRODUCTION_COMPOSER=/opt/c.phar\nDEPLOY_SSH=s\nDEPLOY_PATH=/app\nDEPLOY_PHP=php85\nREMOTE_COMPOSER=c.phar\nGITHUB_TOKEN=t\n");

        $config = ProjectConfig::load($this->directory, 'shop');

        $this->assertSame(['php85', '/opt/c.phar', 'php85', 'c.phar', 't'], [$config->preproductionPhp, $config->preproductionComposer, $config->php, $config->remoteComposer, $config->githubToken]);
    }

    #[Test]
    public function required_values_are_reported_together_with_the_example(): void
    {
        $this->write('shop', "DEPLOY_SSH=shop@prod\n");

        $this->expectException(MissingConfigException::class);
        $this->expectExceptionMessage('PREPRODUCTION_PATH, DEPLOY_PATH. Wzór: ' . ProjectConfig::example());

        ProjectConfig::load($this->directory, 'shop');
    }

    #[Test]
    public function a_relative_preproduction_path_is_rejected(): void
    {
        $this->write('shop', "PREPRODUCTION_PATH=pp-shop\nDEPLOY_SSH=shop@prod\nDEPLOY_PATH=~/shop.pl\n");

        $this->expectException(DeployConfigException::class);
        $this->expectExceptionMessage('ścieżką bezwzględną');

        ProjectConfig::load($this->directory, 'shop');
    }

    #[Test]
    public function a_name_with_a_path_is_rejected(): void
    {
        $this->expectException(DeployConfigException::class);
        $this->expectExceptionMessage('Niepoprawna nazwa projektu');

        ProjectConfig::load($this->directory, '../shop');
    }

    #[Test]
    public function without_projects_the_example_is_suggested(): void
    {
        $this->expectException(MissingConfigException::class);
        $this->expectExceptionMessage('Podaj projekt. Utwórz plik według');

        ProjectConfig::load($this->directory, null);
    }

    #[Test]
    public function available_projects_are_sorted_env_files(): void
    {
        $this->write('shop', '');
        $this->write('blog', '');
        file_put_contents("{$this->directory}/notes.txt", '');

        $this->assertSame(['blog', 'shop'], ProjectConfig::available($this->directory));
    }

    #[Test]
    public function the_install_file_sits_next_to_the_project_config(): void
    {
        $this->write('shop', "PREPRODUCTION_PATH=/pp\nDEPLOY_SSH=s\nDEPLOY_PATH=/app\n");
        $config = ProjectConfig::load($this->directory, 'shop');

        $this->assertNull($config->installSource($this->directory));

        file_put_contents("{$this->directory}/shop.json", '{}');

        $this->assertSame("{$this->directory}/shop.json", $config->installSource($this->directory));
        $this->assertSame('storage/app/shop.install.json', $config->installTarget());
        $this->assertSame(['shop'], ProjectConfig::available($this->directory));
    }

    private function write(string $name, string $contents): void
    {
        file_put_contents("{$this->directory}/{$name}.env", $contents);
    }
}
