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

/*
 * Teksty faktur i integracji z systemem fakturowym (Invoicing\*, docs/baza-danych.md §21).
 */
return [
    'token_required' => 'Podaj token API konta Fakturowni.',
    'subdomain_invalid' => 'Podaj nazwę konta Fakturowni (subdomenę) — małe litery, cyfry i myślniki.',
    'department_invalid' => 'Id działu w Fakturowni to same cyfry.',
    'unauthorized' => 'Fakturownia odrzuciła token API. Sprawdź token i nazwę konta.',
    'connection_failed' => 'Nie udało się połączyć z Fakturownią: :reason',
    'client_without_name' => 'klient bez nazwy',
    'client_duplicate' => 'drugi klient w Fakturowni dla kontrahenta :contractor (duplikat)',
    'account_missing' => 'Organizacja nie ma aktywnego konta Fakturowni.',
    'contractor_foreign' => 'Kontrahent nie należy do tej organizacji.',
    'payment_due_before_issue' => 'Termin płatności nie może być wcześniejszy niż data wystawienia.',
    'currency_invalid' => 'Podaj trzyliterowy kod waluty.',
    'items_required' => 'Dodaj co najmniej jedną pozycję.',
    'item_name_required' => 'Podaj nazwę pozycji.',
    'item_quantity_invalid' => 'Ilość musi być liczbą większą od zera.',
    'item_price_invalid' => 'Podaj cenę netto jako liczbę.',
    'item_tax_invalid' => 'Podaj stawkę VAT od 0 do 100.',
    'send_failed' => 'Nie udało się wysłać faktury do Fakturowni.',
];
