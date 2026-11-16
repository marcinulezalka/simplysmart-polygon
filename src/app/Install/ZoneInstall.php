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
 * Konfiguracja jednej strefy z pliku instalacji po walidacji: uprawnienia, role z pełną (spłaszczoną) listą uprawnień
 * i konta startowe.
 */
final readonly class ZoneInstall
{
    /**
     * @param list<string> $permissions
     * @param array<string, list<string>> $roles Rola => jej uprawnienia (z `*`, `except` i `inherits` rozwiniętymi).
     * @param list<InstallAccount> $accounts
     */
    public function __construct(
        public string $zone,
        public array $permissions,
        public array $roles,
        public array $accounts,
    ) {}
}
