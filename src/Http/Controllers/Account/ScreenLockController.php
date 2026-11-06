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
use Simplysmart\Polygon\Zones\ZoneView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Simplysmart\Polygon\Auth\PinLength;
use Simplysmart\Polygon\Auth\PinRules;
use Simplysmart\Polygon\Auth\ScreenLock;
use Simplysmart\Polygon\Auth\SignIn\PinCheckResult;
use Simplysmart\Polygon\Auth\SignIn\PinVerifier;
use Simplysmart\Polygon\Auth\SignIn\SignOut;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Http\AccountRoutes;
use Simplysmart\Polygon\Zones\ZoneRedirects;

/**
 * Blokada ekranu (jak Win+L, Auth\ScreenLock): zablokowanie bez wylogowania, ekran PIN-u i odblokowanie poprawnym
 * PIN-em (Auth\SignIn\PinVerifier: błędne próby liczą się jak przy logowaniu, a po ich limicie konto jest blokowane
 * i wylogowane). Po odblokowaniu powrót na stronę sprzed blokady, a bez niej na pulpit kontekstu.
 */
final readonly class ScreenLockController
{
    public function __construct(
        private ScreenLock $lock,
        private CurrentZone $zone,
        private CurrentAccount $account,
    ) {}

    public function lock(Request $request): RedirectResponse
    {
        $this->lock->lock($request->session(), $request->headers->get('referer'));

        return new RedirectResponse(route($this->zone->get()->routeName(AccountRoutes::UNLOCK)));
    }

    public function show(): View
    {
        return view($this->zone->get()->view(ZoneView::Unlock), [
            'zone' => $this->zone->get(),
            'name' => $this->account->require()->displayName(),
            'pinLength' => PinLength::accepted(),
        ]);
    }

    public function unlock(Request $request, PinVerifier $pins, SignOut $signOut, ZoneRedirects $redirects): RedirectResponse
    {
        $validated = $request->validate(['pin' => PinRules::forEnteredPin()]);
        $account = $this->account->require();

        return match ($pins->verify($account, is_string($validated['pin']) ? $validated['pin'] : '', $request->ip())) {
            PinCheckResult::Valid => $this->unlocked($request, $redirects),
            PinCheckResult::Invalid => throw ValidationException::withMessages(['pin' => text('account.screen_lock.invalid')]),
            PinCheckResult::Locked => $this->signedOut($request, $signOut),
        };
    }

    private function unlocked(Request $request, ZoneRedirects $redirects): RedirectResponse
    {
        $returnTo = $this->lock->unlock($request->session());
        $request->session()->regenerate();

        return new RedirectResponse($returnTo ?? $redirects->homeFor($this->zone->get()));
    }

    private function signedOut(Request $request, SignOut $signOut): RedirectResponse
    {
        $signOut->handle($request);

        return (new RedirectResponse(route($this->zone->get()->routeName(ZoneRedirects::LOGIN_ROUTE))))->with(StatusMessage::KEY, text('account.screen_lock.locked_out'));
    }
}
