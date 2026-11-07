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
 * Konfiguracja wdrożenia projektu z `infrastructure/deploy/vendors/<projekt>/<projekt>.env` (katalog projektu) albo
 * `infrastructure/deploy/vendors/<projekt>.env` (układ płaski) w repozytorium projektu (pliki ignorowane przez git, bo
 * zawierają sekrety). Katalog projektu ma pierwszeństwo; plik instalacji `<projekt>.json` leży obok znalezionego `.env`.
 *
 * Przed-produkcja to katalog projektu na tym komputerze (`PREPRODUCTION_PATH`, PHP i Composer do skryptu serwerowego),
 * produkcja to serwer SSH (`DEPLOY_SSH`, `DEPLOY_PATH`, `DEPLOY_PHP`, `REMOTE_COMPOSER`, `GITHUB_TOKEN`). Opcjonalne
 * `PREPRODUCTION_RELOAD` i `DEPLOY_RELOAD` to polecenia powłoki uruchamiane na końcu wdrożenia (np. przeładowanie PHP-FPM,
 * żeby opcache nie serwował starego kodu i starego cache tras).
 * Wzór: `resources/deploy/deploy-vendor.env.example` tej paczki.
 */
final readonly class ProjectConfig
{
    /** Katalog konfiguracji projektów względem katalogu repozytorium projektu. */
    public const string DIRECTORY = 'infrastructure/deploy/vendors';

    /** Wzór konfiguracji projektu (w paczce). */
    public const string EXAMPLE = __DIR__ . '/../../../resources/deploy/deploy-vendor.env.example';

    public const string PREPRODUCTION_PATH = 'PREPRODUCTION_PATH';

    public const string PREPRODUCTION_PHP = 'PREPRODUCTION_PHP';

    public const string PREPRODUCTION_COMPOSER = 'PREPRODUCTION_COMPOSER';

    public const string SSH = 'DEPLOY_SSH';

    public const string PATH = 'DEPLOY_PATH';

    public const string PHP = 'DEPLOY_PHP';

    public const string REMOTE_COMPOSER = 'REMOTE_COMPOSER';

    public const string GITHUB_TOKEN = 'GITHUB_TOKEN';

    /** Polecenie po wdrożeniu przed-produkcji (np. przeładowanie PHP-FPM). */
    public const string PREPRODUCTION_RELOAD = 'PREPRODUCTION_RELOAD';

    /** Polecenie po wdrożeniu produkcji na serwerze (np. przeładowanie PHP-FPM). */
    public const string RELOAD = 'DEPLOY_RELOAD';

    /** Domyślne PHP przed-produkcji. */
    public const string DEFAULT_PREPRODUCTION_PHP = 'php';

    /** Domyślne PHP produkcji — jedyne źródło tej wartości (remote.sh wymaga DEPLOY_PHP). */
    public const string DEFAULT_PRODUCTION_PHP = 'php84';

    private const array REQUIRED = [self::PREPRODUCTION_PATH, self::SSH, self::PATH];

    private const string EXTENSION = '.env';

    /** Plik instalacji projektu obok jego konfiguracji: `<projekt>.json` (docs/instalacja.md). */
    private const string INSTALL_EXTENSION = '.json';

    /** Plik instalacji na serwerze i w przed-produkcji, względem katalogu aplikacji (zawartość storage/app jest poza gitem). */
    private const string INSTALL_TARGET = 'storage/app/%s.install.json';

    /** Nazwa projektu: małe litery, cyfry, kropki, myślniki i podkreślenia — bez ścieżek. */
    private const string NAME = '/^[a-z0-9][a-z0-9._-]*$/';

    /**
     * @param  string  $name  Nazwa projektu (nazwa pliku bez `.env`).
     * @param  string  $preproductionPath  Bezwzględna ścieżka klonu projektu na tym komputerze.
     * @param  string  $preproductionPhp  Polecenie PHP 8.4+ przed-produkcji.
     * @param  string|null  $preproductionComposer  Plik Composera przed-produkcji; null — composer z PATH.
     * @param  string  $ssh  Konto SSH serwera produkcji (albo alias z ~/.ssh/config).
     * @param  string  $path  Katalog aplikacji na serwerze (`~/` oznacza katalog domowy).
     * @param  string  $php  Polecenie PHP 8.4+ na serwerze.
     * @param  string|null  $remoteComposer  Plik Composera na serwerze; null — ~/bin/composer.
     * @param  string|null  $githubToken  Token GitHub tylko do odczytu, zapisywany w Composerze serwera.
     * @param  string  $directory  Katalog, w którym leży `<projekt>.env` (katalog projektu albo katalog konfiguracji).
     * @param  string|null  $reload  Polecenie powłoki na serwerze po wdrożeniu (DEPLOY_RELOAD); null — brak.
     * @param  string|null  $preproductionReload  Polecenie po wdrożeniu przed-produkcji (PREPRODUCTION_RELOAD); null — brak.
     */
    public function __construct(
        public string $name,
        public string $preproductionPath,
        public string $preproductionPhp,
        public ?string $preproductionComposer,
        public string $ssh,
        public string $path,
        public string $php,
        public ?string $remoteComposer,
        public ?string $githubToken,
        public string $directory,
        public ?string $reload = null,
        public ?string $preproductionReload = null,
    ) {}

    /**
     * Wczytuje konfigurację projektu.
     *
     * @param  string  $directory  Katalog konfiguracji projektów (`<repozytorium>/` . DIRECTORY).
     * @param  string|null  $name  Nazwa wdrażanego projektu; null — nie podano.
     *
     * @throws DeployConfigException Gdy nazwa jest niepoprawna, plik ma błędną składnię albo PREPRODUCTION_PATH nie jest bezwzględna.
     * @throws MissingConfigException Gdy nie podano projektu, jego pliku nie ma albo brakuje wymaganych kluczy.
     */
    public static function load(string $directory, ?string $name): self
    {
        $example = self::example();

        if ($name === null) {
            throw MissingConfigException::noProject(self::available($directory), $example);
        }

        if (preg_match(self::NAME, $name) !== 1) {
            throw new DeployConfigException("Niepoprawna nazwa projektu: {$name}.");
        }

        $projectDirectory = self::projectDirectory($directory, $name);
        $file = "{$projectDirectory}/{$name}" . self::EXTENSION;

        if (! is_file($file)) {
            throw MissingConfigException::project($name, "{$directory}/{$name}/{$name}" . self::EXTENSION . " ani {$directory}/{$name}" . self::EXTENSION, self::available($directory), $example);
        }

        $values = EnvFile::read($file, $file);
        $missing = array_values(array_filter(self::REQUIRED, fn(string $key): bool => ($values[$key] ?? '') === ''));

        if ($missing !== []) {
            throw MissingConfigException::keys($file, $missing, $example);
        }

        if (! str_starts_with($values[self::PREPRODUCTION_PATH], '/')) {
            throw new DeployConfigException("{$file}: " . self::PREPRODUCTION_PATH . ' musi być ścieżką bezwzględną.');
        }

        return new self(
            name: $name,
            preproductionPath: rtrim($values[self::PREPRODUCTION_PATH], '/'),
            preproductionPhp: self::optional($values, self::PREPRODUCTION_PHP) ?? self::DEFAULT_PREPRODUCTION_PHP,
            preproductionComposer: self::optional($values, self::PREPRODUCTION_COMPOSER),
            ssh: $values[self::SSH],
            path: $values[self::PATH],
            php: self::optional($values, self::PHP) ?? self::DEFAULT_PRODUCTION_PHP,
            remoteComposer: self::optional($values, self::REMOTE_COMPOSER),
            githubToken: self::optional($values, self::GITHUB_TOKEN),
            directory: $projectDirectory,
            reload: self::optional($values, self::RELOAD),
            preproductionReload: self::optional($values, self::PREPRODUCTION_RELOAD),
        );
    }

    /**
     * Projekty z konfiguracją w katalogu konfiguracji (`<projekt>/<projekt>.env` albo `<projekt>.env`), posortowane, bez powtórzeń.
     *
     * @return list<string>
     */
    public static function available(string $directory): array
    {
        $flat = array_map(
            fn(string $file): string => basename($file, self::EXTENSION),
            glob("{$directory}/*" . self::EXTENSION) ?: [],
        );
        $nested = array_map(
            fn(string $projectDirectory): string => basename($projectDirectory),
            array_filter(
                glob("{$directory}/*", GLOB_ONLYDIR) ?: [],
                fn(string $projectDirectory): bool => is_file("{$projectDirectory}/" . basename($projectDirectory) . self::EXTENSION),
            ),
        );
        $names = array_values(array_unique([...$flat, ...$nested]));
        sort($names);

        return $names;
    }

    /**
     * Ścieżka wzoru konfiguracji w komunikatach (bez `..`, gdy plik istnieje).
     */
    public static function example(): string
    {
        return realpath(self::EXAMPLE) ?: self::EXAMPLE;
    }

    /**
     * Ścieżka pliku instalacji projektu obok jego `.env` (`<projekt>.json`), także gdy go nie ma — do opisu planu.
     */
    public function installFile(): string
    {
        return "{$this->directory}/{$this->name}" . self::INSTALL_EXTENSION;
    }

    /**
     * Plik instalacji projektu obok jego `.env` (`<projekt>.json`), jeśli istnieje.
     *
     * @return string|null Ścieżka pliku albo null, gdy projekt go nie ma.
     */
    public function installSource(): ?string
    {
        $file = $this->installFile();

        return is_file($file) ? $file : null;
    }

    /**
     * Ścieżka pliku instalacji w katalogu aplikacji (przed-produkcja i serwer), np. `storage/app/shop.install.json`.
     */
    public function installTarget(): string
    {
        return sprintf(self::INSTALL_TARGET, $this->name);
    }

    /**
     * Cel produkcji w formacie ssh: `konto:katalog`.
     */
    public function productionTarget(): string
    {
        return "{$this->ssh}:{$this->path}";
    }

    /**
     * Katalog z konfiguracją projektu: `<katalog>/<projekt>`, gdy leży w nim `<projekt>.env`, w przeciwnym razie `<katalog>`.
     *
     * @param  string  $directory  Katalog konfiguracji projektów.
     * @param  string  $name  Poprawna nazwa projektu.
     */
    private static function projectDirectory(string $directory, string $name): string
    {
        $nested = "{$directory}/{$name}";

        return is_file("{$nested}/{$name}" . self::EXTENSION) ? $nested : $directory;
    }

    /**
     * @param  array<string, string>  $values
     */
    private static function optional(array $values, string $key): ?string
    {
        $value = $values[$key] ?? '';

        return $value === '' ? null : $value;
    }
}
