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

/**
 * Konto startowe strefy z pliku instalacji: adres, nazwa, role i czy dostaje losowe id z puli seederów (1–999).
 */
final readonly class InstallAccount
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $email,
        public string $name,
        public array $roles,
        public bool $randomId,
    ) {}
}
