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

namespace Simplysmart\Polygon\Http\Controllers\Account;

use Simplysmart\Polygon\Http\StatusMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Simplysmart\Polygon\Auth\AccessRevocationReason;
use Simplysmart\Polygon\Auth\Devices\DeviceCookie;
use Simplysmart\Polygon\Auth\PinLength;
use Simplysmart\Polygon\Auth\Recovery\RecoveryEmailRegistration;
use Simplysmart\Polygon\Auth\RevokeAccountAccess;
use Simplysmart\Polygon\Auth\SignIn\SignOut;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Contracts\TrustedDeviceRepository;
use Simplysmart\Polygon\Http\AccountRoutes;
use Simplysmart\Polygon\Zones\ZoneRedirects;
use Simplysmart\Polygon\Zones\ZoneView;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Bezpieczeństwo zalogowanego konta na jednej stronie: zmiana PIN-u (sekcja `pin`), zapasowy adres e-mail
 * (sekcja `recovery`, RecoveryEmailController), zaufane urządzenia
 * (sekcja `devices`, gdy strefa je ma) i wylogowanie ze wszystkich urządzeń; widok ekranu ZoneView::Security strefy.
 * Dawne adresy sekcji (`account.pin`, `account.devices`) przekierowują do kotwicy na tej stronie.
 */
final readonly class SecurityController
{
    public function __construct(
        private CurrentZone $zone,
        private CurrentAccount $account,
        private Redirector $redirect,
    ) {}

    public function show(Request $request, TrustedDeviceRepository $devices, DeviceCookie $cookie, RecoveryEmailRegistration $recovery): View
    {
        $zone = $this->zone->get();
        $settings = $zone->auth();
        $account = $this->account->require();
        $accountId = $this->account->id() ?? throw new AccessDeniedHttpException();
        return view($zone->view(ZoneView::Security), [
            'zone' => $zone,
            'account' => $account,
            'currentPinLength' => PinLength::accepted(),
            'pinLength' => $settings->pinLength,
            'codeLength' => $settings->codeLength,
            'devices' => $settings->trustedDevicesEnabled ? $devices->activeFor($accountId) : null,
            'currentSelector' => $settings->trustedDevicesEnabled ? $cookie->read($zone, $request)?->selector : null,
            'recoveryEmail' => $account->recoveryEmail(),
            'pendingRecoveryEmail' => $recovery->pending($request),
        ]);
    }

    /**
     * Dawny adres sekcji: przekierowanie do jej kotwicy na stronie bezpieczeństwa.
     */
    public function section(string $section): RedirectResponse
    {
        return self::redirectTo($this->redirect, $this->zone, $section);
    }

    /**
     * Wylogowanie ze wszystkich urządzeń: nowa epoka konta (każda sesja w strefie wygasa), odwołanie zaufanych
     * urządzeń i wylogowanie bieżącej sesji; powrót do logowania z komunikatem.
     */
    public function signOutEverywhere(Request $request, RevokeAccountAccess $revoke, SignOut $signOut): RedirectResponse
    {
        $zone = $this->zone->get();

        $revoke->handle($zone, $this->account->require(), AccessRevocationReason::SignedOutEverywhere);
        $signOut->handle($request);

        return $this->redirect->route($zone->routeName(ZoneRedirects::LOGIN_ROUTE))->with(StatusMessage::KEY, text('account.signed_out_everywhere'));
    }

    /**
     * Strona bezpieczeństwa przewinięta do sekcji (`pin`, `devices`); dla kontrolerów zmian w tych sekcjach.
     */
    public static function redirectTo(Redirector $redirect, CurrentZone $zone, string $section): RedirectResponse
    {
        return $redirect->to($redirect->getUrlGenerator()->route($zone->get()->routeName(AccountRoutes::SECURITY)) . '#' . $section);
    }
}
