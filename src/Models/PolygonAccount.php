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

use Simplysmart\Polygon\Auth\Concerns\HasZoneAccountColumns;
use Simplysmart\Polygon\Zones\PolygonZone;
use Spatie\Permission\Traits\HasRoles;

/**
 * Konto strefy `polygon` (tabela `polygon_accounts` z migracji `create_polygon_tables`), jak `AdmiroAccount`
 * w admiro: kolumny standardowe, e-mail szyfrowany z `email_hash`, role i uprawnienia spatie w guardzie strefy.
 * Konta frontendu są w `users` (Models\User).
 *
 * @property int $id
 * @property string $token
 * @property string $name
 * @property string $email
 * @property string $email_hash
 * @property string|null $pin_hash
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class PolygonAccount extends MainAuthenticatable
{
    use HasRoles;
    use HasZoneAccountColumns;

    public const string TABLE = PolygonZone::PROVIDER;

    protected $table = self::TABLE;

    protected string $guard_name = PolygonZone::GUARD;
}
