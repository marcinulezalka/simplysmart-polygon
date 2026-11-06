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

namespace Simplysmart\Polygon\Contracts;

/**
 * Wartość z etykietą dla człowieka w bieżącym języku (statusy, typy — enumy paczek).
 */
interface HasLabel
{
    public function label(): string;
}
