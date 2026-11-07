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

namespace Simplysmart\Polygon\Models\Permissions;

use Illuminate\Database\Eloquent\SoftDeletes;
use Simplysmart\Polygon\Models\Concerns\HasChecksum;
use Simplysmart\Polygon\Models\Concerns\HasRouteToken;
use Simplysmart\Polygon\Models\Concerns\HasStandardColumns;
use Simplysmart\Polygon\Permissions\PermissionTables;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Rola (guard w `guard_name`), wspólna dla całej aplikacji (bez zespołów).
 *
 * Spatie wymaga dziedziczenia po swoim modelu, więc standard DataModel (token, casty, checksum, soft delete)
 * dają te same traity paczki. Usunięty rekord dalej zajmuje swoją nazwę w guardzie (unikalny indeks).
 *
 * @property int $id
 * @property string $token
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $status
 * @property bool $is_active
 * @property bool $is_default
 * @property int $sort_order
 * @property string $guard_name
 * @property array<array-key, mixed>|null $json_attributes
 * @property array<array-key, mixed>|null $json_default_settings
 * @property list<string>|null $json_tags
 * @property array<array-key, mixed>|null $json_migrations
 * @property string|null $checksum
 * @property array<array-key, mixed>|null $json_archive
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class Role extends SpatieRole
{
    use HasChecksum;
    use HasRouteToken;
    use HasStandardColumns;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            PermissionTables::GUARD_NAME => 'string',
            PermissionTables::TEAM_FOREIGN_KEY => 'integer',
        ];
    }
}
