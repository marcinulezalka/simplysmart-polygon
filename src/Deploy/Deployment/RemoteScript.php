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

namespace Simplysmart\Polygon\Deploy\Deployment;

/**
 * Skrypt `remote.sh` z eksportami konfiguracji i przejściem do katalogu aplikacji, oraz polecenia, które go
 * uruchamiają przez `bash -s` (standardowe wejście, więc token nie trafia do listy procesów).
 *
 * Ten sam skrypt aktualizuje przed-produkcję na tym komputerze i produkcję przez ssh. Przed-produkcja dostaje
 * `DEPLOY_KEEP_ENV=1` (remote.sh zostawia jej `.env` i bierze lokalnego Composera) i nie dostaje tokenu GitHub,
 * więc nie trafia on do globalnej konfiguracji Composera dewelopera. Gdy deploy przesłał manifest wdrożeniowy,
 * skrypt dostaje jego nazwę (`DEPLOY_COMPOSER_MANIFEST`) i instaluje paczki z niego zamiast z `composer.lock`, a gdy
 * przesłał plik instalacji projektu — jego ścieżkę (`DEPLOY_INSTALL_FILE`), którą `polygon:install` dopisuje do
 * `POLYGON_INSTALL_FILES` z `.env`.
 */
final readonly class RemoteScript
{
    public const string SERVER_SCRIPT = __DIR__ . '/../../../resources/deploy/remote.sh';

    /** Plik środowiska produkcji na serwerze, kopiowany przez remote.sh jako `.env`. */
    public const string PRODUCTION_ENV = '.env.production';

    private const string SHELL = 'bash';

    private const string READ_STDIN = '-s';

    private const string SSH = 'ssh';

    /** Zmienne środowiska czytane przez remote.sh. */
    private const string ENV_PHP = 'DEPLOY_PHP';

    private const string ENV_COMPOSER = 'COMPOSER_BIN';

    private const string ENV_GITHUB_TOKEN = 'GITHUB_TOKEN';

    private const string ENV_KEEP_ENV = 'DEPLOY_KEEP_ENV';

    private const string ENV_MANIFEST = 'DEPLOY_COMPOSER_MANIFEST';

    private const string ENV_INSTALL_FILE = 'DEPLOY_INSTALL_FILE';

    /**
     * @param  string  $serverScript  Treść remote.sh.
     */
    public function __construct(
        private string $serverScript,
    ) {}

    /**
     * Wczytuje remote.sh z katalogu narzędzi wdrożeniowych.
     *
     * @throws DeployException Gdy pliku nie ma albo nie da się go odczytać.
     */
    public static function fromFile(string $path = self::SERVER_SCRIPT): self
    {
        $script = is_file($path) ? file_get_contents($path) : false;

        return new self($script !== false ? $script : throw new DeployException("Brak skryptu serwerowego {$path}."));
    }

    /**
     * Polecenie uruchamiające skrypt lokalnie (przed-produkcja).
     *
     * @return list<string>
     */
    public function localCommand(): array
    {
        return [self::SHELL, self::READ_STDIN];
    }

    /**
     * Polecenie uruchamiające skrypt na serwerze produkcji.
     *
     * @return list<string>
     */
    public function remoteCommand(ProjectConfig $config): array
    {
        return [self::SSH, $config->ssh, implode(' ', $this->localCommand())];
    }

    /**
     * Skrypt produkcji: PHP, Composer i token GitHub serwera.
     *
     * @param  string|null  $manifest  Manifest wdrożeniowy w katalogu aplikacji; null — paczki z `composer.lock`.
     * @param  string|null  $installFile  Plik instalacji w katalogu aplikacji; null — tylko pliki z `.env`.
     */
    public function production(ProjectConfig $config, ?string $manifest = null, ?string $installFile = null): string
    {
        return $this->script(
            [self::ENV_PHP => $config->php, self::ENV_GITHUB_TOKEN => $config->githubToken ?? '', self::ENV_COMPOSER => $config->remoteComposer, self::ENV_MANIFEST => $manifest, self::ENV_INSTALL_FILE => $installFile],
            $config->path,
        );
    }

    /**
     * Skrypt przed-produkcji: PHP i Composer przed-produkcji, `DEPLOY_KEEP_ENV=1`, bez tokenu GitHub.
     *
     * @param  string|null  $manifest  Manifest wdrożeniowy w katalogu przed-produkcji; null — paczki z `composer.lock`.
     * @param  string|null  $installFile  Plik instalacji w katalogu przed-produkcji; null — tylko pliki z `.env`.
     */
    public function preproduction(ProjectConfig $config, ?string $manifest = null, ?string $installFile = null): string
    {
        return $this->script(
            [self::ENV_PHP => $config->preproductionPhp, self::ENV_KEEP_ENV => '1', self::ENV_COMPOSER => $config->preproductionComposer, self::ENV_MANIFEST => $manifest, self::ENV_INSTALL_FILE => $installFile],
            $config->preproductionPath,
        );
    }

    /**
     * @param  array<string, string|null>  $variables  Eksportowane zmienne; null — zmienna nie jest eksportowana.
     * @param  string  $path  Katalog aplikacji.
     */
    private function script(array $variables, string $path): string
    {
        $lines = [];

        foreach (array_filter($variables, fn(?string $value): bool => $value !== null) as $name => $value) {
            $lines[] = "export {$name}=" . ShellArgument::quote($value);
        }

        $lines[] = 'cd ' . ShellArgument::path($path) . ' || exit 1';
        $lines[] = rtrim($this->serverScript);

        return implode("\n", $lines) . "\n";
    }
}
