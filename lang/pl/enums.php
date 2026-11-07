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

return [
    'tax_rate_exempt_short' => 'zw',
    'record_status' => [
        'draft' => 'Szkic',
        'pending' => 'Oczekuje',
        'active' => 'Aktywny',
        'blocked' => 'Zablokowany',
        'migrating' => 'Migracja',
        'quarantined' => 'Kwarantanna',
        'archived' => 'Zarchiwizowany',
    ],
    'tax_rate_type' => [
        'standard' => 'Podstawowa',
        'reduced' => 'Obniżona',
        'super-reduced' => 'Super obniżona',
        'zero' => 'Zerowa',
        'exempt' => 'Zwolniona (zw)',
    ],
    'measure_unit_kind' => [
        'count' => 'Sztuki',
        'mass' => 'Masa',
        'length' => 'Długość',
        'area' => 'Powierzchnia',
        'volume' => 'Objętość',
        'time' => 'Czas',
        'energy' => 'Energia',
        'service' => 'Usługa',
        'packaging' => 'Opakowanie',
    ],
    'resource_kind' => [
        'room' => 'Gabinet',
        'equipment' => 'Sprzęt',
        'other' => 'Inny zasób',
    ],
    'booking_status' => [
        'booked' => 'zapisana',
        'confirmed' => 'potwierdzona',
        'cancelled' => 'odwołana',
        'no_show' => 'nieobecność',
    ],
    'continent' => [
        'europe' => 'Europa',
        'asia' => 'Azja',
        'north-america' => 'Ameryka Północna',
        'south-america' => 'Ameryka Południowa',
        'africa' => 'Afryka',
        'oceania' => 'Oceania',
        'antarctica' => 'Antarktyda',
    ],
    'exchange_rate_source' => [
        'nbp' => 'NBP (tabela A)',
        'manual' => 'ręczny',
    ],
    'holiday_source' => [
        'nager' => 'kalendarz świąt',
        'manual' => 'ręczny',
    ],
];
