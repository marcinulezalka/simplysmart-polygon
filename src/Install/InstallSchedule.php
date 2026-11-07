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
 * Wpis grafiku organizacji z pliku instalacji po walidacji (`organizations.<klucz>.schedule[]`): klucz zasobu
 * (`resources[].key` tej organizacji albo jej przodka), wykonawca (strefa i adres konta), dzień tygodnia ISO,
 * przedział „HH:MM”, długość slotu i opcjonalny okres ważności (RRRR-MM-DD; bez początku — dzień instalacji).
 */
final readonly class InstallSchedule
{
    public function __construct(
        public string $resource,
        public string $performerZone,
        public string $performerEmail,
        public int $weekday,
        public string $from,
        public string $to,
        public int $slot,
        public ?string $validFrom = null,
        public ?string $validTo = null,
        public int $weekInterval = 1,
    ) {}
}
