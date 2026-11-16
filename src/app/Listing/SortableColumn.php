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

use BackedEnum;

/**
 * Sortowanie listy po kolumnie jako enum z wartościami `<kolumna>` (rosnąco) i `-<kolumna>` (malejąco) w parametrze
 * adresu PARAMETER; kliknięcie nagłówka kolumny (toggled()) sortuje po niej rosnąco, a ponowne — malejąco. Wspólna
 * implementacja: trait SortsByColumn.
 */
interface SortableColumn extends BackedEnum
{
    /** Parametr adresu z sortowaniem. */
    public const string PARAMETER = 'sort';

    /** Przedrostek wartości sortowania malejącego. */
    public const string DESCENDING = '-';

    /**
     * Etykieta kolumny w nagłówku listy.
     */
    public function label(): string;

    public function isDescending(): bool;

    /**
     * Kolumna sortowania (przypadek rosnący).
     */
    public function column(): self;

    /**
     * Sortowanie po kliknięciu nagłówka tej kolumny przy bieżącym $current: ta sama kolumna rosnąco — malejąco,
     * inaczej rosnąco.
     */
    public function toggled(?self $current): self;
}
