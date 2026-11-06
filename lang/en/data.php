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

/*
| Etykiety kolumn dla PackageText::data($model, $kolumna): najpierw "<model>.<kolumna>", potem wspólne "<kolumna>".
*/

return [
    'id' => 'ID',
    'token' => 'Token',
    'parent_id' => 'Parent',
    'name' => 'Name',
    'status' => 'Status',
    'is_active' => 'Active',
    'sort_order' => 'Order',
    'json_attributes' => 'Additional attributes',
    'json_default_settings' => 'Default settings',
    'json_tags' => 'Tags',
    'json_i18n' => 'Translations',
    'json_migrations' => 'Integration data',
    'checksum' => 'Checksum',
    'json_archive' => 'Archive',
    'created_at' => 'Created',
    'updated_at' => 'Updated',
    'deleted_at' => 'Deleted',
    'code' => 'Code',
    'is_default' => 'Default',
    'currency' => [
        'name' => 'Currency name',
        'alpha_code_3' => 'Currency code (ISO 4217)',
        'numeric_code' => 'Numeric code (ISO 4217)',
        'decimals' => 'Decimal places',
        'is_default' => 'Application currency',
    ],
    'language' => [
        'name' => 'Language name',
        'code' => 'Language code (ISO 639)',
        'native_name' => 'Native name',
        'is_default' => 'Application language',
        'is_interface' => 'Interface language',
        'flag_icon' => 'Flag icon',
    ],
    'country' => [
        'name' => 'Country name',
        'alpha_code_2' => 'Country code (ISO 3166-1 alpha-2)',
        'alpha_code_3' => 'Country code (ISO 3166-1 alpha-3)',
        'numeric_code' => 'Numeric code (ISO 3166-1)',
        'dial_code' => 'Dialling code',
        'currency_id' => 'Currency',
        'is_eu' => 'EU member',
        'is_default' => 'Application country',
        'flag_icon' => 'Flag icon',
        'continent' => 'Continent',
        'region_code' => 'Region (UN M49)',
        'population' => 'Population',
    ],
    'country_language' => [
        'country_id' => 'Country',
        'language_id' => 'Language',
        'is_primary' => 'Primary language',
    ],
    'tax_rate' => [
        'name' => 'Rate',
        'country_id' => 'Country',
        'type' => 'Rate type',
        'rate' => 'Rate (%)',
        'valid_from' => 'Valid from',
        'valid_to' => 'Valid to',
        'note' => 'Note',
    ],
    'measure_unit' => [
        'name' => 'Name',
        'country_id' => 'Country',
        'code' => 'Symbol',
        'unece_code' => 'UN/ECE code',
        'kind' => 'Kind',
    ],
    'organization_unit_type' => [
        'name' => 'Type name',
        'code' => 'Code',
        'number_prefix' => 'Number prefix',
        'icon' => 'Icon',
        'is_internal' => 'Internal structure',
        'is_public' => 'In the public schedule',
    ],
    'organization' => [
        'unit_type_id' => 'Unit type',
        'short_name' => 'Short name',
        'name' => 'Organization name',
        'parent_id' => 'Parent organization',
        'is_department' => 'Department',
        'nip' => 'Tax ID (NIP)',
        'regon' => 'REGON',
        'krs' => 'KRS',
        'street' => 'Street and number',
        'postal_code' => 'Postal code',
        'city' => 'City',
        'country_code' => 'Country',
        'color' => 'Colour',
        'phone' => 'Landline phone',
        'mobile_phone' => 'Mobile phone',
        'email' => 'E-mail',
        'billing_email' => 'Billing e-mail',
    ],
    'organization_member' => [
        'organization_id' => 'Organization',
        'model_type' => 'Account type',
        'model_id' => 'Account',
    ],
    'role' => [
        'name' => 'Role name',
        'guard_name' => 'Zone (guard)',
    ],
    'permission' => [
        'name' => 'Permission name',
        'guard_name' => 'Zone (guard)',
    ],
    'exchange_rate' => [
        'name' => 'Rate',
        'currency_id' => 'Currency',
        'rate_date' => 'Rate date',
        'rate' => 'Rate (PLN per 1 unit)',
        'source' => 'Source',
        'table_number' => 'NBP table number',
    ],
    'holiday' => [
        'name' => 'Name',
        'country_id' => 'Country',
        'holiday_date' => 'Date',
        'source' => 'Source',
        'is_day_off' => 'Day off work',
    ],
    'user' => [
        'name' => 'Full name',
        'email' => 'E-mail',
        'email_verified_at' => 'E-mail verified',
        'password' => 'Password',
        'remember_token' => 'Remember token',
    ],
    'polygon_account' => [
        'name' => 'Full name',
        'email' => 'E-mail',
        'email_hash' => 'E-mail index',
        'pin_hash' => 'PIN',
        'pin_set_at' => 'PIN set',
        'pin_failed_attempts' => 'Failed PIN attempts',
        'lock_level' => 'Lock level',
        'locked_until' => 'Locked until',
        'auth_epoch' => 'Sign-in epoch',
        'last_login_at' => 'Last sign-in',
        'last_login_ip' => 'Last sign-in IP',
        'recovery_email' => 'Recovery e-mail',
        'recovery_email_set_at' => 'Recovery e-mail confirmed',
        'is_active' => 'Sign-in enabled',
    ],
];
