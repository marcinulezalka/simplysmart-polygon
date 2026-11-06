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

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Simplysmart\Polygon\Models\Concerns\HasChecksum;
use Simplysmart\Polygon\Models\Concerns\HasStandardColumns;

/**
 * Baza modeli kont stref (każda strefa ma własną tabelę kont i własny model). Tabela kont ma pełny standard
 * tabel danych (Database\ZoneTables), więc model dostaje to samo co DataModel: casty kolumn standardowych,
 * checksum, soft delete, scope'y active() i default() oraz defaultSetting().
 */
abstract class MainAuthenticatable extends MainModel implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;
    use HasChecksum;
    use HasStandardColumns;
    use SoftDeletes;
}
