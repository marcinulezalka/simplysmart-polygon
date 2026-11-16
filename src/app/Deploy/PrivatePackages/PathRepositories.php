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

use stdClass;

/**
 * Wzorce katalogów lokalnych paczek projektu: adresy repozytoriów Composera typu `path` z `composer.json`
 * (np. `../packages/*\/*` — paczki obok projektu). Lokalnie są to dowiązania, a na serwerze tych katalogów nie ma,
 * więc DeployManifest zastępuje je repozytoriami git paczek.
 */
final class PathRepositories
{
    /** Typ repozytorium Composera z katalogiem lokalnym. */
    public const string TYPE = 'path';

    /**
     * Wzorce z `repositories` manifestu (tylko typ `path` z adresem tekstowym).
     *
     * @return list<string>
     */
    public static function fromManifest(stdClass $manifest): array
    {
        $patterns = [];

        foreach (self::repositories($manifest) as $repository) {
            if (self::isPath($repository) && $repository instanceof stdClass && is_string($repository->url ?? null)) {
                $patterns[] = $repository->url;
            }
        }

        return $patterns;
    }

    /**
     * Repozytoria manifestu bez repozytoriów `path`.
     *
     * @return list<mixed>
     */
    public static function withoutPath(stdClass $manifest): array
    {
        return array_values(array_filter(self::repositories($manifest), static fn(mixed $repository): bool => ! self::isPath($repository)));
    }

    /**
     * @return list<mixed>
     */
    private static function repositories(stdClass $manifest): array
    {
        return is_array($manifest->repositories ?? null) ? array_values($manifest->repositories) : [];
    }

    private static function isPath(mixed $repository): bool
    {
        return $repository instanceof stdClass && ($repository->type ?? null) === self::TYPE;
    }
}
