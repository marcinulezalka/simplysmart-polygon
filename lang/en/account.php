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

    'menu' => 'Account',
    'pin_title' => 'Change PIN',
    'pin_intro' => 'Send a code to your e-mail address, then enter your current PIN, the code and a new PIN (:min to :max digits).',
    'send_code' => 'Send code',
    'recovery' => [
        'none' => 'No recovery e-mail',
        'title' => 'Recovery e-mail',
        'current' => 'Confirmed recovery address',
        'email' => 'Recovery e-mail',
        'new_email' => 'New recovery e-mail',
        'same_as_primary' => 'The recovery address must differ from the account address.',
        'code_sent' => 'We have sent a code to the recovery address. Enter it to confirm the address.',
        'code_hint' => 'Code sent to :email.',
        'confirm' => 'Confirm address',
        'saved' => 'Recovery e-mail saved.',
        'removed' => 'Recovery e-mail removed.',
    ],
    'code_sent' => 'We have sent a code to the account address.',
    'current_pin' => 'Current PIN',
    'new_pin' => 'New PIN',
    'pin_invalid' => 'The current PIN is incorrect.',
    'code_invalid' => 'The code is incorrect or has expired. Send a new one.',
    'pin_same' => 'The new PIN must differ from the current one.',
    'pin_changed' => 'PIN changed. Your other sessions were signed out and remembered devices revoked.',
    'devices_title' => 'Trusted devices',
    'devices_empty' => 'You have no remembered devices.',
    'device' => 'Device',
    'device_last_used' => 'Last used',
    'device_expires' => 'Valid until',
    'this_device' => 'this device',
    'device_revoke' => 'Revoke',
    'devices_revoke_all' => 'Revoke all',
    'devices_revoked' => 'The device is no longer trusted: signing in on it requires an e-mail code again.',
    'pin_length' => 'PIN: :min–:max digits',
    'security_title' => 'Security',
    'sign_out_everywhere' => 'Sign out of all devices',
    'signed_out_everywhere' => 'Signed out of all devices.',
    'screen_lock' => [
        'title' => 'Screen locked',
        'lead' => ':name, enter your PIN to get back to work.',
        'pin' => 'PIN',
        'submit' => 'Unlock',
        'invalid' => 'Incorrect PIN.',
        'locked_out' => 'Too many wrong PIN attempts — the account was locked and signed out.',
        'sign_out' => 'Sign out',
    ],
    'organization' => [
        'switch' => 'Switch organization',
        'current' => 'Current organization',
        'switched' => 'You are now working in :name.',
        'unavailable' => 'This organization is not available to your account.',
        'none' => 'Your account does not belong to any organization yet.',
        'default' => 'Default after sign-in',
        'make_default' => 'Set as default',
        'default_saved' => 'After signing in you will work in :name.',
    ],

];
