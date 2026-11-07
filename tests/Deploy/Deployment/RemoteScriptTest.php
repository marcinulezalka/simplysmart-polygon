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
use Simplysmart\Polygon\Deploy\Deployment\ProjectConfig;
use Simplysmart\Polygon\Deploy\Deployment\RemoteScript;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Skrypt dla przed-produkcji i produkcji: eksporty w apostrofach, katalog aplikacji i polecenia uruchomienia.
 */
final class RemoteScriptTest extends TestCase
{
    #[Test]
    public function production_exports_the_server_php_composer_and_token(): void
    {
        $this->assertSame(
            "export DEPLOY_PHP='php84'\nexport GITHUB_TOKEN='it'\\''s'\nexport COMPOSER_BIN='composer.phar'\ncd ~/'shop.pl' || exit 1\necho remote\n",
            (new RemoteScript("echo remote\n"))->production($this->config()),
        );
    }

    #[Test]
    public function preproduction_keeps_env_and_never_gets_the_token(): void
    {
        $this->assertSame(
            "export DEPLOY_PHP='php'\nexport DEPLOY_KEEP_ENV='1'\ncd '/var/www/pp shop' || exit 1\necho remote\n",
            (new RemoteScript("echo remote\n"))->preproduction($this->config()),
        );
    }

    #[Test]
    public function a_deploy_manifest_is_passed_to_both_environments(): void
    {
        $script = new RemoteScript("echo remote\n");

        $this->assertStringContainsString("export DEPLOY_COMPOSER_MANIFEST='composer.deploy.json'\n", $script->production($this->config(), 'composer.deploy.json'));
        $this->assertStringContainsString("export DEPLOY_COMPOSER_MANIFEST='composer.deploy.json'\n", $script->preproduction($this->config(), 'composer.deploy.json'));
    }

    #[Test]
    public function an_install_file_is_passed_to_both_environments(): void
    {
        $script = new RemoteScript("echo remote\n");

        $this->assertStringContainsString("export DEPLOY_INSTALL_FILE='storage/app/shop.install.json'\n", $script->production($this->config(), null, 'storage/app/shop.install.json'));
        $this->assertStringContainsString("export DEPLOY_INSTALL_FILE='storage/app/shop.install.json'\n", $script->preproduction($this->config(), null, 'storage/app/shop.install.json'));
    }

    #[Test]
    public function the_server_script_applies_install_files_after_migrations(): void
    {
        $script = RemoteScript::fromFile()->production($this->config());

        $this->assertGreaterThan((int) strpos($script, 'artisan migrate --force'), (int) strpos($script, 'artisan polygon:organizations:seed'));
        $this->assertGreaterThan((int) strpos($script, 'artisan polygon:organizations:seed'), (int) strpos($script, 'artisan polygon:install'));
        $this->assertLessThan((int) strpos($script, 'artisan config:cache'), (int) strpos($script, 'artisan polygon:install'));
    }

    #[Test]
    public function the_server_script_publishes_package_assets_before_caching(): void
    {
        $script = RemoteScript::fromFile()->production($this->config());

        $this->assertGreaterThan((int) strpos($script, 'artisan polygon:install'), (int) strpos($script, 'artisan polygon:assets'));
        $this->assertLessThan((int) strpos($script, 'artisan config:cache'), (int) strpos($script, 'artisan polygon:assets'));
    }

    #[Test]
    public function fresh_is_passed_only_when_asked_and_the_script_seeds_a_fresh_database(): void
    {
        $script = RemoteScript::fromFile();

        $this->assertStringContainsString("export DEPLOY_FRESH='1'\n", $script->production($this->config(), null, null, true));
        $this->assertStringContainsString("export DEPLOY_FRESH='1'\n", $script->preproduction($this->config(), null, null, true));
        $this->assertStringNotContainsString('export DEPLOY_FRESH', $script->production($this->config()));
        $this->assertStringNotContainsString('export DEPLOY_FRESH', $script->preproduction($this->config()));

        $server = $script->production($this->config(), null, null, true);
        $this->assertGreaterThan((int) strpos($server, 'artisan migrate:fresh --force'), (int) strpos($server, 'POLYGON_INSTALL_FILES="$install_files" "$php_bin" artisan db:seed --force'));
        $this->assertLessThan((int) strpos($server, 'artisan config:cache'), (int) strpos($server, 'artisan db:seed --force'));
    }

    #[Test]
    public function the_reload_command_is_passed_only_when_configured(): void
    {
        $script = new RemoteScript("echo remote\n");
        $config = new ProjectConfig('shop', '/pp', 'php', null, 'shop@prod', '~/shop.pl', 'php84', null, null, '/vendors', 'sudo systemctl reload php8.4-fpm', 'touch /tmp/pp');

        $this->assertStringContainsString("export DEPLOY_RELOAD='sudo systemctl reload php8.4-fpm'\n", $script->production($config));
        $this->assertStringContainsString("export DEPLOY_RELOAD='touch /tmp/pp'\n", $script->preproduction($config));
        $this->assertStringNotContainsString('DEPLOY_RELOAD', $script->production($this->config()));
    }

    #[Test]
    public function the_server_script_clears_caches_before_migrations_and_rebuilds_them_at_the_end(): void
    {
        $script = RemoteScript::fromFile()->production($this->config());
        $migrate = (int) strpos($script, 'artisan migrate --force');

        foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear', 'clear-compiled'] as $clear) {
            $this->assertLessThan($migrate, (int) strpos($script, 'artisan ' . $clear), $clear);
        }
        $this->assertGreaterThan($migrate, (int) strpos($script, 'artisan optimize:clear'), 'Magazyn cache czyszczony po migracjach.');
        $this->assertGreaterThan((int) strpos($script, 'artisan view:cache'), (int) strpos($script, 'artisan queue:restart'));
        $this->assertGreaterThan((int) strpos($script, 'artisan queue:restart'), (int) strpos($script, 'bash -c "$reload"'));
    }

    #[Test]
    public function commands_read_the_script_from_standard_input(): void
    {
        $script = new RemoteScript('');

        $this->assertSame(['bash', '-s'], $script->localCommand());
        $this->assertSame(['ssh', 'shop@prod', 'bash -s'], $script->remoteCommand($this->config()));
    }

    #[Test]
    public function the_server_script_is_read_from_the_deploy_directory(): void
    {
        $this->assertStringContainsString('git pull --ff-only', RemoteScript::fromFile()->production($this->config()));
    }

    #[Test]
    public function a_missing_server_script_is_reported(): void
    {
        $this->expectException(DeployException::class);

        RemoteScript::fromFile('/nonexistent/remote.sh');
    }

    private function config(): ProjectConfig
    {
        return new ProjectConfig('shop', '/var/www/pp shop', 'php', null, 'shop@prod', '~/shop.pl', 'php84', 'composer.phar', "it's", '/vendors');
    }
}
