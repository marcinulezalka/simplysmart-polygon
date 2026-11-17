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
    'types' => [
        'sign_in' => 'Signed in',
        'sign_in_rejected' => 'Sign-in rejected (wrong PIN, attempt :attempts)',
        'signed_out' => 'Signed out',
        'account_locked' => 'Account locked until :until',
        'access_revoked' => 'Sessions on other devices ended',
        'pin_changed' => 'PIN changed',
        'pin_reset' => 'PIN reset',
        'recovery_email_set' => 'Recovery e-mail set',
        'recovery_email_removed' => 'Recovery e-mail removed',
        'trusted_device_registered' => 'Trusted device added: :device',
        'trusted_devices_revoked' => 'Trusted devices removed (:count)',
        'status' => 'Notification',
    ],
    'history' => [
        'insert' => 'Added: :subject',
        'update' => 'Changed: :subject',
        'delete' => 'Deleted: :subject',
    ],
    'tables' => [
        'zone_accounts' => 'your account',
        'organizations' => 'organization',
        'organization_members' => 'organization membership',
        'organization_resources' => 'organization resource',
        'organization_unit_types' => 'unit type',
        'model_has_roles' => 'account role',
        'model_has_permissions' => 'account permission',
        'roles' => 'role',
        'permissions' => 'permission',
        'role_has_permissions' => 'role permission',
        'resource_bookings' => 'booking',
        'resource_schedules' => 'schedule',
        'resource_leases' => 'lease',
        'contractors' => 'contractor',
        'contractor_contacts' => 'contractor contact',
        'contractor_contracts' => 'contractor contract',
        'invoices' => 'invoice',
        'invoice_items' => 'invoice item',
        'invoicing_accounts' => 'invoicing account',
        'invoicing_contractor_links' => 'contractor invoicing link',
        'users' => 'user account',
        'polygon_accounts' => 'account',
    ],
    'empty' => 'No events.',
];
