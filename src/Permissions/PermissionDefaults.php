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

namespace Simplysmart\Polygon\Permissions;

use Illuminate\Contracts\Config\Repository;
use Simplysmart\Polygon\Models\Permissions\Permission;
use Simplysmart\Polygon\Models\Permissions\Role;

/**
 * Konfiguracja spatie/laravel-permission ustawiana przez paczkę (bez publikowania config/permission.php): modele
 * paczki z kolumnami standardowymi, nazwy tabel i kolumn z PermissionTables, bez zespołów.
 *
 * Wywoływana po register() wszystkich providerów (config spatie jest już scalony), pojedynczymi kluczami.
 */
final readonly class PermissionDefaults
{
    public function __construct(
        private Repository $config,
    ) {}

    public function apply(): void
    {
        $this->config->set([
            'permission.models.permission' => Permission::class,
            'permission.models.role' => Role::class,
            'permission.teams' => false,
            'permission.column_names.role_pivot_key' => PermissionTables::ROLE_FOREIGN_KEY,
            'permission.column_names.permission_pivot_key' => PermissionTables::PERMISSION_FOREIGN_KEY,
            'permission.column_names.model_morph_key' => PermissionTables::MODEL_ID,
            'permission.table_names.permissions' => PermissionTables::PERMISSIONS,
            'permission.table_names.roles' => PermissionTables::ROLES,
            'permission.table_names.model_has_permissions' => PermissionTables::MODEL_HAS_PERMISSIONS,
            'permission.table_names.model_has_roles' => PermissionTables::MODEL_HAS_ROLES,
            'permission.table_names.role_has_permissions' => PermissionTables::ROLE_HAS_PERMISSIONS,
        ]);
    }
}
