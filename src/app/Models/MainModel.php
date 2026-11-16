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

namespace Simplysmart\Polygon\Models;

use Illuminate\Database\Eloquent\Model;
use Simplysmart\Polygon\Models\Concerns\HasRouteToken;

/**
 * Baza modeli tabel aplikacji (docs/baza-danych.md §5): klucz trasy to losowy `token`.
 */
abstract class MainModel extends Model
{
    use HasRouteToken;
}
