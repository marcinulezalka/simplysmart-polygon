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
 * Jeden plik instalacji po walidacji: katalog ról dla każdej strefy (sekcja `roles`) i konfiguracje stref (`zones`).
 */
final readonly class InstallFile
{
    /**
     * @param array<string, bool> $catalog Rola katalogu => czy dostaje wszystkie uprawnienia strefy (`"*"`); false = żadnych.
     * @param list<ZoneInstall> $zones
     */
    public function __construct(
        public array $catalog,
        public array $zones,
    ) {}
}
