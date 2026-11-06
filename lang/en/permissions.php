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

return [

    'polygon' => [
        'zones' => ['view' => 'View zones', 'configure' => 'Configure zones'],
        'accounts' => ['view' => 'View zone accounts', 'manage' => 'Manage zone accounts (PIN reset, unlock, deactivation)'],
        'organizations' => ['view' => 'View organizations and their members', 'manage' => 'Manage organizations and memberships'],
        'owners' => ['view' => 'View zone owners', 'assign' => 'Assign zone owners'],
        'settings' => ['view' => 'View system settings', 'manage' => 'Change system settings'],
        'roles' => ['manage' => 'Manage roles and permissions'],
        'dictionaries' => ['view' => 'View dictionaries', 'manage' => 'Manage dictionaries'],
        'users' => ['manage' => 'Manage administrators'],
        'audit' => ['view' => 'View the audit log'],
        'heroes' => ['manage' => 'Manage super administrators'],
        'system' => ['maintenance' => 'System maintenance'],
    ],

];
