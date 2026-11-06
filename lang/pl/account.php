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

    'menu' => 'Konto',
    'pin_title' => 'Zmień PIN',
    'pin_intro' => 'Wyślij kod na swój adres e-mail, a potem wpisz obecny PIN, kod i nowy PIN (od :min do :max cyfr).',
    'send_code' => 'Wyślij kod',
    'code_sent' => 'Wysłaliśmy kod na adres konta.',
    'recovery' => [
        'none' => 'Brak adresu awaryjnego',
        'title' => 'Zapasowy adres e-mail',
        'current' => 'Potwierdzony zapasowy adres',
        'email' => 'Zapasowy adres e-mail',
        'new_email' => 'Nowy zapasowy adres e-mail',
        'same_as_primary' => 'Zapasowy adres musi być inny niż adres konta.',
        'code_sent' => 'Wysłaliśmy kod na zapasowy adres. Wpisz go, żeby potwierdzić adres.',
        'code_hint' => 'Kod wysłany na :email.',
        'confirm' => 'Potwierdź adres',
        'saved' => 'Zapasowy adres e-mail zapisany.',
        'removed' => 'Zapasowy adres e-mail usunięty.',
    ],
    'current_pin' => 'Obecny PIN',
    'new_pin' => 'Nowy PIN',
    'pin_invalid' => 'Obecny PIN jest nieprawidłowy.',
    'code_invalid' => 'Kod jest nieprawidłowy albo wygasł. Wyślij nowy.',
    'pin_same' => 'Nowy PIN musi różnić się od obecnego.',
    'pin_changed' => 'PIN zmieniony. Pozostałe sesje zostały wylogowane, a zapamiętane urządzenia odwołane.',
    'devices_title' => 'Zaufane urządzenia',
    'devices_empty' => 'Nie masz zapamiętanych urządzeń.',
    'device' => 'Urządzenie',
    'device_last_used' => 'Ostatnio użyte',
    'device_expires' => 'Ważne do',
    'this_device' => 'to urządzenie',
    'device_revoke' => 'Odwołaj',
    'devices_revoke_all' => 'Odwołaj wszystkie',
    'devices_revoked' => 'Urządzenie nie jest już zaufane: logowanie na nim znowu wymaga kodu z e-maila.',
    'pin_length' => 'PIN: :min–:max cyfr',
    'security_title' => 'Bezpieczeństwo',
    'sign_out_everywhere' => 'Wyloguj ze wszystkich urządzeń',
    'signed_out_everywhere' => 'Wylogowano ze wszystkich urządzeń.',
    'screen_lock' => [
        'title' => 'Ekran zablokowany',
        'lead' => ':name, wpisz PIN, żeby wrócić do pracy.',
        'pin' => 'PIN',
        'submit' => 'Odblokuj',
        'invalid' => 'Nieprawidłowy PIN.',
        'locked_out' => 'Za dużo błędnych prób PIN-u — konto zostało zablokowane i wylogowane.',
        'sign_out' => 'Wyloguj się',
    ],

];
