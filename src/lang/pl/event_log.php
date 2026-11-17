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
        'sign_in' => 'Zalogowano',
        'sign_in_rejected' => 'Odrzucono logowanie (błędny PIN, próba :attempts)',
        'signed_out' => 'Wylogowano',
        'account_locked' => 'Konto zablokowane do :until',
        'access_revoked' => 'Zakończono sesje na innych urządzeniach',
        'pin_changed' => 'Zmieniono PIN',
        'pin_reset' => 'Zresetowano PIN',
        'recovery_email_set' => 'Ustawiono zapasowy adres e-mail',
        'recovery_email_removed' => 'Usunięto zapasowy adres e-mail',
        'trusted_device_registered' => 'Dodano zaufane urządzenie: :device',
        'trusted_devices_revoked' => 'Usunięto zaufane urządzenia (:count)',
        'status' => 'Powiadomienie',
    ],
    'history' => [
        'insert' => 'Dodano: :subject',
        'update' => 'Zmieniono: :subject',
        'delete' => 'Usunięto: :subject',
    ],
    'tables' => [
        'zone_accounts' => 'Twoje konto',
        'organizations' => 'organizacja',
        'organization_members' => 'członkostwo w organizacji',
        'organization_resources' => 'zasób organizacji',
        'organization_unit_types' => 'typ jednostki',
        'model_has_roles' => 'rola konta',
        'model_has_permissions' => 'uprawnienie konta',
        'roles' => 'rola',
        'permissions' => 'uprawnienie',
        'role_has_permissions' => 'uprawnienie roli',
        'resource_bookings' => 'rezerwacja',
        'resource_schedules' => 'grafik',
        'resource_leases' => 'umowa najmu',
        'contractors' => 'kontrahent',
        'contractor_contacts' => 'kontakt kontrahenta',
        'contractor_contracts' => 'umowa z kontrahentem',
        'invoices' => 'faktura',
        'invoice_items' => 'pozycja faktury',
        'invoicing_accounts' => 'konto fakturowania',
        'invoicing_contractor_links' => 'powiązanie kontrahenta z fakturownią',
        'users' => 'konto użytkownika',
        'polygon_accounts' => 'konto',
    ],
    'empty' => 'Brak zdarzeń.',
];
