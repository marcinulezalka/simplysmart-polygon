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

namespace Simplysmart\Polygon\Listing;

/**
 * Implementacja SortableColumn dla enumu sortowania (`kolumna` / `-kolumna`): kolumny listy, kierunek, kolumna
 * i przełączenie po kliknięciu nagłówka. Enum dodaje tylko przypadki i label().
 *
 * @phpstan-require-implements SortableColumn
 */
trait SortsByColumn
{
    /**
     * Kolumny listy (przypadki rosnące) w kolejności enumu.
     *
     * @return list<self>
     */
    public static function columns(): array
    {
        return array_values(array_filter(self::cases(), static fn(self $sort): bool => ! $sort->isDescending()));
    }

    public function isDescending(): bool
    {
        return str_starts_with((string) $this->value, SortableColumn::DESCENDING);
    }

    public function column(): self
    {
        return self::from(ltrim((string) $this->value, SortableColumn::DESCENDING));
    }

    public function toggled(?SortableColumn $current): self
    {
        $ascending = $this->column();

        return $current === $ascending ? self::from(SortableColumn::DESCENDING . $ascending->value) : $ascending;
    }
}
