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

namespace Simplysmart\Polygon\Auth\SignIn;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Simplysmart\Polygon\Auth\Devices\RegisterTrustedDevice;
use Simplysmart\Polygon\Auth\Events\SignInSucceeded;
use Simplysmart\Polygon\Auth\SignIn\Steps\EmailCodeStep;
use Simplysmart\Polygon\Auth\SignIn\Steps\PinSetupStep;
use Simplysmart\Polygon\Contracts\AccountStore;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Zones\ZoneRedirects;
use Simplysmart\Polygon\Zones\ZoneUrlKey;

/**
 * Wszystkie kroki wykonane: logowanie w guardzie strefy, nowa sesja z epoką konta, zapis
 * udanego logowania (zeruje błędy i blokady), zapamiętanie urządzenia, jeśli konto o to poprosiło,
 * i powrót na zamierzoną stronę albo stronę startową konta (Zones\ZoneRedirects::homeFor(): Contracts\ZoneHome strefy,
 * np. pulpit roli, a bez niej dashboard strefy). Pierwsze logowanie (z ustawieniem PIN-u) zawsze kończy się na stronie
 * startowej: zamierzona strona sprzed logowania jest pomijana, bo nowe konto zaczyna od pulpitu swojej roli, a nie od
 * strony, którą ktoś otworzył przed nim. W strefie z `url_key` zamierzona strona dostaje hash bieżącej sesji
 * (Zones\ZoneUrlKey::rewrite()) — zapamiętana z hashem poprzedniej sesji dawałaby 404.
 */
final readonly class CompleteSignIn
{
    public const string EPOCH_SESSION_KEY = 'auth_epoch';

    /** Klucz sesji Laravela z zamierzoną stroną (Redirector::intended()). */
    private const string INTENDED_SESSION_KEY = 'url.intended';

    public function __construct(
        private AuthManager $auth,
        private AccountStore $store,
        private SignInFlowState $state,
        private PendingSignInResolver $pending,
        private CurrentZone $zone,
        private Dispatcher $events,
        private Redirector $redirect,
        private RegisterTrustedDevice $devices,
        private ZoneRedirects $redirects,
        private ZoneUrlKey $urlKeys,
    ) {}

    public function handle(PendingSignIn $signIn, Request $request): RedirectResponse
    {
        $account = $signIn->account ?? $this->pending->abort();
        $zone = $this->zone->get();

        $guard = $this->auth->guard($zone->guard);

        if (! $guard instanceof StatefulGuard) {
            throw new \LogicException("Zone [{$zone->name}] guard must be a session guard.");
        }

        $guard->login($account);
        $request->session()->regenerate();
        $request->session()->put(self::EPOCH_SESSION_KEY, $account->authEpoch());
        $this->store->recordSignIn($account, $request->ip());

        if ($zone->auth()->trustedDevicesEnabled && $signIn->wantsDeviceRemembered(EmailCodeStep::KEY)) {
            $this->devices->handle($account, $request);
        }

        $this->state->clear();

        $this->events->dispatch(new SignInSucceeded($zone->name, $account, $request->ip()));

        $home = $this->redirects->homeFor($zone, $account instanceof ZoneAccount ? $account : null);

        if ($signIn->hasCompleted(PinSetupStep::KEY)) {
            $request->session()->forget(self::INTENDED_SESSION_KEY);

            return $this->redirect->to($home);
        }

        $intended = $request->session()->get(self::INTENDED_SESSION_KEY);

        if ($zone->urlKey && is_string($intended)) {
            $request->session()->put(self::INTENDED_SESSION_KEY, $this->urlKeys->rewrite($intended, $zone->slug, $request->session()));
        }

        return $this->redirect->intended($home);
    }
}
