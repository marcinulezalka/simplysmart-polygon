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

use Simplysmart\Polygon\Deploy\Process\ProcessRunner;

/**
 * `composer update` wskazanych paczek z innym plikiem manifestu (zmienna COMPOSER, przez `env`), ze strumieniami
 * bieżącego procesu. Uruchomiony jako skrypt composera używa tego samego pliku composera (COMPOSER_BINARY), inaczej
 * `composer` z PATH.
 */
final readonly class ComposerUpdate
{
    private const string ENV = 'env';

    public function __construct(
        private ProcessRunner $processes,
    ) {}

    /**
     * @param string $root Katalog projektu.
     * @param string $manifest Plik manifestu względem $root (np. composer.private.json).
     * @param list<string> $packages Paczki do aktualizacji.
     * @param list<string> $options Dodatkowe opcje composera (np. `--no-install`).
     *
     * @return int Kod wyjścia composera.
     */
    public function run(string $root, string $manifest, array $packages, array $options = []): int
    {
        $binary = getenv('COMPOSER_BINARY');
        $composer = is_string($binary) && $binary !== '' ? [PHP_BINARY, $binary] : ['composer'];

        return $this->processes->run([self::ENV, 'COMPOSER=' . $manifest, ...$composer, 'update', ...$packages, ...$options, '--no-interaction'], null, $root);
    }
}
