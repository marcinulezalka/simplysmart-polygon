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
 * Wynik synchronizacji kontrahentów z systemem fakturowym: ilu kontrahentów założono z pobranych klientów, ilu
 * zaktualizowano danymi z Fakturowni, ilu wysłano do Fakturowni, ilu było bez zmian i których pominięto (nazwa
 * z powodem — np. klient bez nazwy albo NIP zajęty przez usuniętego kontrahenta).
 */
final class ContractorSyncReport
{
    public int $created = 0;

    public int $updated = 0;

    public int $pushed = 0;

    public int $unchanged = 0;

    /**
     * @var list<array{name: string, reason: string}>
     */
    public array $skipped = [];

    public function skip(string $name, string $reason): void
    {
        $this->skipped[] = ['name' => $name, 'reason' => $reason];
    }
}
