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

namespace Simplysmart\Polygon\Install;

use RuntimeException;

/**
 * Plik instalacji jest nieczytelny albo niezgodny ze schematem (docs/instalacja.md); nic nie zostało zapisane.
 */
final class InvalidInstallFile extends RuntimeException
{
    /** Źródło błędów wykrytych po połączeniu wszystkich plików (nie da się wskazać jednego pliku). */
    public const string MERGED_FILES = 'install files';

    public static function in(string $path, string $reason): self
    {
        return new self("Install file [{$path}]: {$reason}");
    }

    /**
     * Błąd połączonych plików instalacji (np. odwołanie do organizacji albo roli z innego pliku).
     */
    public static function merged(string $reason): self
    {
        return self::in(self::MERGED_FILES, $reason);
    }
}
