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

namespace Simplysmart\Polygon\Http;

use Illuminate\Routing\Router;
use Simplysmart\Polygon\Http\Controllers\Account\PinChangeController;
use Simplysmart\Polygon\Http\Controllers\Account\RecoveryEmailController;
use Simplysmart\Polygon\Http\Controllers\Account\ScreenLockController;
use Simplysmart\Polygon\Http\Controllers\Account\SecurityController;
use Simplysmart\Polygon\Http\Controllers\Account\TrustedDeviceController;
use Simplysmart\Polygon\Zones\AccessTier;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Trasy konta zalogowanego w strefie (poziom Authenticated, pod `account/`): blokada i odblokowanie ekranu oraz strona
 * bezpieczeństwa — zmiana PIN-u, zapasowy adres e-mail, w strefie z zaufanymi urządzeniami ich odwołanie i wylogowanie
 * wszędzie (dawne `pin` i `devices` przekierowują do sekcji strony). Każda zmiana ma limit `polygon.sign-in`.
 */
final readonly class AccountRoutes
{
    public const string PIN = 'account.pin';

    public const string PIN_CODE = 'account.pin.code';

    public const string PIN_UPDATE = 'account.pin.update';

    /** Zapasowy adres e-mail: kod na nowy adres, potwierdzenie, usunięcie (sekcja `#recovery`). */
    public const string RECOVERY_EMAIL_CODE = 'account.recovery-email.code';

    public const string RECOVERY_EMAIL_UPDATE = 'account.recovery-email.update';

    public const string RECOVERY_EMAIL_DESTROY = 'account.recovery-email.destroy';

    public const string DEVICES = 'account.devices';

    public const string DEVICES_REVOKE = 'account.devices.revoke';

    public const string DEVICES_REVOKE_ALL = 'account.devices.revoke-all';

    /** Strona bezpieczeństwa: PIN, zapasowy adres, zaufane urządzenia, wylogowanie wszędzie. */
    public const string SECURITY = 'account.security';

    /** Blokada ekranu (POST), ekran PIN-u i odblokowanie (Http\Controllers\Account\ScreenLockController). */
    public const string LOCK = 'account.lock';

    public const string UNLOCK = 'account.unlock';

    public const string UNLOCK_STORE = 'account.unlock.store';

    public const string SIGN_OUT_EVERYWHERE = 'account.security.sign-out-everywhere';

    /** Kotwice sekcji strony bezpieczeństwa (`#pin`, `#devices`, `#recovery`, `#lock`). */
    public const string SECTION_PIN = 'pin';

    public const string SECTION_DEVICES = 'devices';

    public const string SECTION_RECOVERY = 'recovery';

    public const string SECTION_LOCK = 'lock';

    private const string PREFIX = 'account';

    public function __construct(
        private Router $router,
    ) {}

    public function register(ZoneDefinition $zone): void
    {
        $throttle = 'throttle:' . SignInRoutes::THROTTLE;

        $this->router->middleware(AccessTier::Authenticated->middleware($zone))->prefix(self::PREFIX)->group(function () use ($zone, $throttle): void {
            $this->router->post('lock', [ScreenLockController::class, 'lock'])->name(self::LOCK);
            $this->router->get('unlock', [ScreenLockController::class, 'show'])->name(self::UNLOCK);
            $this->router->post('unlock', [ScreenLockController::class, 'unlock'])->middleware($throttle)->name(self::UNLOCK_STORE);

            $this->router->get('security', [SecurityController::class, 'show'])->name(self::SECURITY);
            $this->router->delete('security/sessions', [SecurityController::class, 'signOutEverywhere'])->middleware($throttle)->name(self::SIGN_OUT_EVERYWHERE);
            $this->router->get('pin', [SecurityController::class, 'section'])->defaults('section', self::SECTION_PIN)->name(self::PIN);
            $this->router->post('pin/code', [PinChangeController::class, 'sendCode'])->middleware($throttle)->name(self::PIN_CODE);
            $this->router->put('pin', [PinChangeController::class, 'update'])->middleware($throttle)->name(self::PIN_UPDATE);
            $this->router->post('recovery-email/code', [RecoveryEmailController::class, 'sendCode'])->middleware($throttle)->name(self::RECOVERY_EMAIL_CODE);
            $this->router->put('recovery-email', [RecoveryEmailController::class, 'update'])->middleware($throttle)->name(self::RECOVERY_EMAIL_UPDATE);
            $this->router->delete('recovery-email', [RecoveryEmailController::class, 'destroy'])->middleware($throttle)->name(self::RECOVERY_EMAIL_DESTROY);

            if ($zone->auth()->trustedDevicesEnabled) {
                $this->router->get('devices', [SecurityController::class, 'section'])->defaults('section', self::SECTION_DEVICES)->name(self::DEVICES);
                $this->router->delete('devices', [TrustedDeviceController::class, 'destroyAll'])->middleware($throttle)->name(self::DEVICES_REVOKE_ALL);
                $this->router->delete('devices/{device}', [TrustedDeviceController::class, 'destroy'])->middleware($throttle)->name(self::DEVICES_REVOKE);
            }
        });
    }
}
