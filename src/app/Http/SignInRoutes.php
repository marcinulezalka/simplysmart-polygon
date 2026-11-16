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
use Simplysmart\Polygon\Auth\SignIn\SignInFlow;
use Simplysmart\Polygon\Auth\SignIn\Steps\EmailCodeStep;
use Simplysmart\Polygon\Http\Controllers\Auth\ForgotPinController;
use Simplysmart\Polygon\Http\Controllers\Auth\LoginController;
use Simplysmart\Polygon\Http\Controllers\Auth\LogoutController;
use Simplysmart\Polygon\Http\Controllers\Auth\ResendCodeController;
use Simplysmart\Polygon\Http\Controllers\Auth\SignInStepController;
use Simplysmart\Polygon\Http\Middleware\EnsureSignInStep;
use Simplysmart\Polygon\Zones\AccessTier;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Trasy logowania strefy (wewnątrz grupy strefy): `auth.login`, `auth.<krok>` dla każdego kroku
 * z configu, `auth.code.resend`, „Nie pamiętam PIN-u” z resetem przez zapasowy adres i `auth.logout`. Każdy POST ma limit `polygon.sign-in`.
 */
final readonly class SignInRoutes
{
    public const string THROTTLE = 'polygon.sign-in';

    private const string PREFIX = 'signin';

    /** Strona „Nie pamiętam PIN-u” (nazwa względem prefiksu `auth.`): jak ustawić nowy PIN, reset przez zapasowy adres. */
    public const string FORGOT_PIN = 'forgot-pin';

    /** Kod resetu PIN-u na zapasowy adres, reset kodem i rezygnacja (nazwy względem prefiksu `auth.`). */
    public const string FORGOT_PIN_CODE = 'forgot-pin.code';

    public const string FORGOT_PIN_RESET = 'forgot-pin.reset';

    public const string FORGOT_PIN_CANCEL = 'forgot-pin.cancel';

    private const string FORGOT_PIN_PATH = 'forgot-pin';

    public function __construct(
        private Router $router,
        private SignInFlow $flow,
    ) {}

    /**
     * Nazwa trasy kroku względem prefiksu strefy.
     */
    public static function stepRoute(string $step): string
    {
        return 'auth.' . $step;
    }

    public function register(ZoneDefinition $zone): void
    {
        $throttle = 'throttle:' . self::THROTTLE;

        $this->router->middleware(AccessTier::GuestOnly->middleware($zone))->prefix(self::PREFIX)->name('auth.')->group(function () use ($zone, $throttle): void {
            $this->router->get('/', [LoginController::class, 'create'])->name('login');
            $this->router->post('/', [LoginController::class, 'store'])->middleware($throttle)->name('login.store');
            $this->router->get(self::FORGOT_PIN_PATH, [ForgotPinController::class, 'show'])->name(self::FORGOT_PIN);
            $this->router->post(self::FORGOT_PIN_PATH . '/code', [ForgotPinController::class, 'sendCode'])->middleware($throttle)->name(self::FORGOT_PIN_CODE);
            $this->router->post(self::FORGOT_PIN_PATH, [ForgotPinController::class, 'reset'])->middleware($throttle)->name(self::FORGOT_PIN_RESET);
            $this->router->delete(self::FORGOT_PIN_PATH . '/code', [ForgotPinController::class, 'cancel'])->middleware($throttle)->name(self::FORGOT_PIN_CANCEL);

            foreach ($this->flow->steps($zone->auth()) as $step) {
                $key = $step->key();
                $guard = EnsureSignInStep::class . ':' . $key;

                $this->router->get($key, [SignInStepController::class, 'show'])->defaults('step', $key)->middleware($guard)->name($key);
                $this->router->post($key, [SignInStepController::class, 'store'])->defaults('step', $key)->middleware([$guard, $throttle])->name($key . '.store');

                if ($key === EmailCodeStep::KEY) {
                    $this->router->post($key . '/resend', ResendCodeController::class)->middleware([$guard, $throttle])->name($key . '.resend');
                }
            }
        });

        $this->router->middleware(AccessTier::Authenticated->middleware($zone))->post(self::PREFIX . '/out', LogoutController::class)->name('auth.logout');
    }
}
