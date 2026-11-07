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
 * Organizacja z pliku instalacji po walidacji: klucz z pliku (identyfikator organizacji założonej przez instalację,
 * zapisany w `json_attributes.install_key`), nazwa, opcjonalnie nazwa skrócona, organizacja nadrzędna (klucz), kod typu
 * jednostki (`organization_unit_types.code`), flaga departamentu, dane firmy (kolumny Organization::DETAIL_LENGTHS)
 * zasoby organizacji (gabinety, sprzęt), jej grafik (kalendarz zasobów), flaga kooperacji (podorganizacja najemcy)
 * i umowy najmu jej zasobów.
 */
final readonly class InstallOrganization
{
    /**
     * @param array<string, string> $details Kolumna danych firmy => wartość.
     * @param list<InstallResource> $resources Zasoby organizacji z pliku.
     * @param list<InstallSchedule> $schedules Grafik organizacji z pliku.
     * @param list<InstallLease> $leases Umowy najmu zasobów organizacji z pliku.
     */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $shortName,
        public ?string $parent,
        public ?string $unitType,
        public bool $isDepartment,
        public array $details,
        public array $resources = [],
        public array $schedules = [],
        public bool $isCooperation = false,
        public array $leases = [],
    ) {}
}
