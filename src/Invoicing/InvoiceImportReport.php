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

namespace Simplysmart\Polygon\Invoicing;

/**
 * Wynik pobrania faktur z systemu fakturowego (ImportInvoices): ile faktur pobrano po raz pierwszy, ile zaktualizowano
 * (zmienione w Fakturowni albo — dla wystawionych w aplikacji — nowy stan, np. zapłacona), ile było bez zmian, ilu
 * nabywców założono jako kontrahentów i które faktury pominięto (numer z powodem).
 */
final class InvoiceImportReport
{
    public int $created = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    public int $contractorsCreated = 0;

    /**
     * @var list<array{name: string, reason: string}>
     */
    public array $skipped = [];

    public function skip(string $name, string $reason): void
    {
        $this->skipped[] = ['name' => $name, 'reason' => $reason];
    }
}
