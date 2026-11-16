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

namespace Simplysmart\Polygon\Deploy\PrivatePackages;

use JsonException;
use stdClass;

/**
 * Manifest wdrożeniowy (`composer.deploy.json`): `composer.json` bez repozytoriów typu path, z repozytorium VCS
 * (adres `origin`) każdej paczki z katalogów tych repozytoriów, którą projekt wymaga (`require`, `require-dev`
 * albo paczki prywatne), i z dopisanymi paczkami prywatnymi. Lokalnie paczki zostają dowiązaniami z katalogów,
 * a na serwerze Composer pobiera je z ich repozytoriów git.
 */
final readonly class DeployManifest
{
    private const string VCS_TYPE = 'vcs';

    private const string PACKAGE_MANIFEST = 'composer.json';

    /**
     * @param  string  $root  Katalog projektu.
     */
    public function __construct(
        private string $root,
        private RepositorySource $repositories,
    ) {}

    /**
     * Paczki z katalogów repozytoriów path wymagane przez projekt: nazwa => katalog. Projekt bez `composer.json`
     * albo bez repozytoriów path nie ma takich paczek.
     *
     * @param  array<string, string>  $private  Paczki prywatne (nazwa => wersja).
     * @return array<string, string>
     *
     * @throws PrivatePackagesException Gdy composer.json projektu albo paczki jest nieczytelny.
     */
    public function packages(array $private): array
    {
        if (! is_file($this->path(PrivatePackages::PUBLIC_MANIFEST))) {
            return [];
        }

        $manifest = $this->decode($this->path(PrivatePackages::PUBLIC_MANIFEST));
        $required = [...array_keys(self::map($manifest->require ?? null)), ...array_keys(self::map($manifest->{'require-dev'} ?? null)), ...array_keys($private)];
        $packages = [];

        foreach (PathRepositories::fromManifest($manifest) as $pattern) {
            foreach (glob($this->path($pattern), GLOB_ONLYDIR) ?: [] as $directory) {
                $file = $directory . '/' . self::PACKAGE_MANIFEST;
                $name = is_file($file) ? ($this->decode($file)->name ?? null) : null;

                if (is_string($name) && in_array($name, $required, true)) {
                    $packages[$name] = $directory;
                }
            }
        }

        ksort($packages);

        return $packages;
    }

    /**
     * Treść composer.deploy.json.
     *
     * @param  array<string, string>  $private  Paczki prywatne (nazwa => wersja).
     * @param  array<string, string>  $packages  Paczki z katalogów (nazwa => katalog), zob. packages().
     *
     * @throws PrivatePackagesException Gdy paczka nie ma repozytorium z `origin` albo manifest jest nieczytelny.
     */
    public function manifest(array $private, array $packages): string
    {
        $manifest = $this->decode($this->path(PrivatePackages::PUBLIC_MANIFEST));
        $require = $manifest->require ?? new stdClass();

        if (! $require instanceof stdClass) {
            throw new PrivatePackagesException('Pole "require" w ' . PrivatePackages::PUBLIC_MANIFEST . ' musi być obiektem.');
        }

        foreach ($private as $name => $constraint) {
            $require->{$name} = $constraint;
        }

        $repositories = PathRepositories::withoutPath($manifest);

        foreach ($packages as $name => $directory) {
            $url = $this->repositories->originUrl($directory)
                ?? throw new PrivatePackagesException("Paczka {$name} ({$directory}) nie ma repozytorium git z origin.");
            $repositories[] = (object) ['type' => self::VCS_TYPE, 'url' => $url];
        }

        $manifest->require = $require;
        $manifest->repositories = $repositories;

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * Paczki z commitami niewypchniętymi (lock wskazałby starszy kod z repozytorium zdalnego).
     *
     * @param  array<string, string>  $packages  Nazwa => katalog.
     * @return list<string>
     */
    public function unpushed(array $packages): array
    {
        return array_keys(array_filter($packages, $this->repositories->hasUnpushedCommits(...)));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function map(mixed $value): array
    {
        return $value instanceof stdClass ? get_object_vars($value) : [];
    }

    private function decode(string $file): stdClass
    {
        $contents = is_file($file) ? file_get_contents($file) : false;

        if ($contents === false) {
            throw new PrivatePackagesException("Brak pliku {$file}.");
        }

        try {
            $decoded = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PrivatePackagesException("Plik {$file} nie jest poprawnym JSON-em: {$exception->getMessage()}.");
        }

        return $decoded instanceof stdClass ? $decoded : throw new PrivatePackagesException("Plik {$file} musi zawierać obiekt JSON.");
    }

    private function path(string $file): string
    {
        return rtrim($this->root, '/') . '/' . $file;
    }
}
