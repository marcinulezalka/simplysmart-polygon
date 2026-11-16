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

namespace Simplysmart\Polygon\View;

use Illuminate\Contracts\Foundation\Application;
use Simplysmart\Polygon\Deploy\Release\Changelog;

/**
 * Wersja aplikacji do pokazania w interfejsie (np. plakietka na stronie startowej): najnowsze wydanie z CHANGELOG.md
 * projektu (Deploy\Release\Changelog), więc po `composer release` zmienia się sama, bez edycji widoków.
 */
final readonly class ApplicationVersion
{
    public const string CHANGELOG = 'CHANGELOG.md';

    public function __construct(
        private Application $app,
    ) {}

    /**
     * Najnowsza wydana wersja (np. „0.2.0”) albo null, gdy nie ma CHANGELOG.md lub żadnego wydania.
     */
    public function current(): ?string
    {
        $path = $this->app->basePath(self::CHANGELOG);
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            return null;
        }

        $version = (new Changelog($contents))->latestRelease();

        return $version === null ? null : (string) $version;
    }
}
