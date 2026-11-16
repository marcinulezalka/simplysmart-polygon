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
    'pin_reset_title' => 'Resetuj PIN',
    'pin_reset_intro' => 'Reset usuwa obecny PIN — nowy ustawisz przy następnym logowaniu. Tak to przebiega:',
    'pin_reset_step_confirm' => 'Rozwiń „Resetuj PIN”, wpisz obecny PIN i potwierdź.',
    'pin_reset_step_sign_out' => 'PIN zostaje usunięty, a konto wylogowane ze wszystkich urządzeń; zapamiętane urządzenia są odwołane.',
    'pin_reset_step_sign_in' => 'Zaloguj się ponownie adresem e-mail i kodem, który przyjdzie na ten adres.',
    'pin_reset_step_new_pin' => 'Po kodzie ustaw nowy PIN (od :min do :max cyfr) — od tej chwili logujesz się nim jak dotąd.',
    'pin_reset_start' => 'Resetuj PIN',
    'pin_reset_sign_out_notice' => 'Uwaga: po potwierdzeniu nastąpi natychmiastowe wylogowanie — zapisz najpierw otwartą pracę.',
    'pin_reset_confirm' => 'Resetuj PIN i wyloguj',
    'pin_reset_done' => 'PIN został zresetowany i nastąpiło wylogowanie ze wszystkich urządzeń. Zaloguj się kodem z e-maila i ustaw nowy PIN.',
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
    'organization' => [
        'switch' => 'Przełącz organizację',
        'current' => 'Bieżąca organizacja',
        'switched' => 'Pracujesz teraz w organizacji :name.',
        'unavailable' => 'Ta organizacja nie jest dostępna dla Twojego konta.',
        'none' => 'Twoje konto nie należy jeszcze do żadnej organizacji.',
        'default' => 'Domyślna po zalogowaniu',
        'make_default' => 'Ustaw jako domyślną',
        'default_saved' => 'Po zalogowaniu będziesz pracować w organizacji :name.',
    ],

];
