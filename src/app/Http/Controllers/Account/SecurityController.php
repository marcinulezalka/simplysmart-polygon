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
use Simplysmart\Polygon\Auth\Devices\TrustedDevicesOverview;
use Simplysmart\Polygon\Auth\PinFormSettings;
use Simplysmart\Polygon\Auth\Recovery\RecoveryEmailRegistration;
use Simplysmart\Polygon\Auth\RevokeAccountAccess;
use Simplysmart\Polygon\Auth\SignIn\SignOut;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Http\SecurityPage;
use Simplysmart\Polygon\Zones\ZoneRedirects;
use Simplysmart\Polygon\Zones\ZoneView;

/**
 * Bezpieczeństwo zalogowanego konta na jednej stronie: zmiana PIN-u (sekcja `pin`), zapasowy adres e-mail
 * (sekcja `recovery`, RecoveryEmailController), zaufane urządzenia
 * (sekcja `devices`, gdy strefa je ma) i wylogowanie ze wszystkich urządzeń; widok ekranu ZoneView::Security strefy.
 * Dawne adresy sekcji (`account.pin`, `account.devices`) przekierowują do miejsca sekcji (Http\SecurityPage).
 */
final readonly class SecurityController
{
    public function __construct(
        private CurrentZone $zone,
        private CurrentAccount $account,
        private Redirector $redirect,
        private SecurityPage $page,
    ) {}

    public function show(Request $request, TrustedDevicesOverview $devices, PinFormSettings $pin, RecoveryEmailRegistration $recovery): View
    {
        $zone = $this->zone->get();
        $account = $this->account->require();

        return view($zone->view(ZoneView::Security), [
            'zone' => $zone,
            'account' => $account,
            ...$pin->toArray(),
            ...$devices->forRequest($request),
            'recoveryEmail' => $account->recoveryEmail(),
            'pendingRecoveryEmail' => $recovery->pending($request),
        ]);
    }

    /**
     * Dawny adres sekcji: przekierowanie do jej miejsca (Http\SecurityPage).
     */
    public function section(string $section): RedirectResponse
    {
        return $this->page->redirect($this->zone->get(), $section);
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
}
