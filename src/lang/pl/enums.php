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
    'color_theme' => [
        'auto' => 'Automatyczny',
        'light' => 'Jasny',
        'dark' => 'Ciemny',
        'wcag' => 'WCAG',
        'wcag-contrast' => 'WCAG duży kontrast',
    ],
    'booking_status' => [
        'booked' => 'zapisana',
        'confirmed' => 'potwierdzona',
        'completed' => 'wykonana',
        'cancelled' => 'odwołana',
        'no_show' => 'nieobecność',
    ],
    'lease_mode' => [
        'windows' => 'okna na wyłączność',
        'on_demand' => 'na żądanie (wolne terminy)',
    ],
    'lease_access' => [
        'book' => 'zapis wizyt',
        'view' => 'podgląd grafiku',
        'wallboard' => 'tablica publiczna',
    ],
    'contract_status' => [
        'upcoming' => 'przyszła',
        'active' => 'obowiązuje',
        'expired' => 'wygasła',
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
    'invoicing_provider' => [
        'fakturownia' => 'Fakturownia',
    ],
    'invoice_kind' => [
        'vat' => 'faktura VAT',
        'proforma' => 'pro forma',
    ],
    'invoice_status' => [
        'draft' => 'robocza',
        'queued' => 'w kolejce do wysyłki',
        'sent' => 'wysłana',
        'failed' => 'błąd wysyłki',
    ],
    'invoice_source' => [
        'local' => 'wystawiona w aplikacji',
        'fakturownia' => 'z Fakturowni',
    ],
    'invoicing_sync_trigger' => [
        'automatic' => 'automatyczna',
        'manual' => 'na żądanie',
    ],
    'invoice_tax' => [
        'rate' => 'stawka VAT',
        'exempt' => 'zwolniona (zw)',
        'not_applicable' => 'nie podlega (np)',
    ],
    'payment_method' => [
        'transfer' => 'przelew',
        'cash' => 'gotówka',
        'card' => 'karta',
    ],
];
