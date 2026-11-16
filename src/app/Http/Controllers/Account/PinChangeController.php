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

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use Simplysmart\Polygon\Auth\ChangePin;
use Simplysmart\Polygon\Auth\PinRules;
use Simplysmart\Polygon\Auth\ResetOwnPin;
use Simplysmart\Polygon\Auth\SignIn\IssueSignInCode;
use Simplysmart\Polygon\Auth\SignIn\LoginAttemptLimiter;
use Simplysmart\Polygon\Auth\SignIn\SignOut;
use Simplysmart\Polygon\Auth\SignInLimit;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Http\AccountRoutes;
use Simplysmart\Polygon\Http\StatusMessage;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Zones\ZoneRedirects;

/**
 * Zmiana PIN-u zalogowanego konta (formularz na stronie bezpieczeństwa, SecurityController): wysłanie kodu na adres
 * konta i zapis nowego PIN-u albo reset PIN-u obecnym PIN-em z wylogowaniem (nowy PIN przy następnym logowaniu).
 */
final readonly class PinChangeController
{
    public function __construct(
        private CurrentZone $zone,
        private CurrentAccount $current,
        private Redirector $redirect,
    ) {}

    public function sendCode(Request $request, LoginAttemptLimiter $limiter, Dispatcher $bus): RedirectResponse
    {
        $account = $this->account();

        $limiter->attempt(SignInLimit::AccountCodeRequests, $account->emailAddress(), 'code');
        $bus->dispatch(new IssueSignInCode($this->zone->get()->name, $account->emailAddress(), $request->ip(), $request->userAgent()));

        return $this->back()->with(StatusMessage::KEY, text('account.code_sent'));
    }

    public function update(Request $request, ChangePin $change): RedirectResponse
    {
        $settings = $this->zone->get()->auth();
        $validated = $request->validate([
            'current_pin' => PinRules::forEnteredPin(),
            'code' => ['required', 'string', 'digits:' . $settings->codeLength],
            'pin' => PinRules::forNewPin($settings->pinLength),
        ]);

        $change->handle(
            $this->account(),
            is_string($validated['current_pin']) ? $validated['current_pin'] : '',
            is_string($validated['code']) ? $validated['code'] : '',
            is_string($validated['pin']) ? $validated['pin'] : '',
            $request,
        );

        return $this->back()->with(StatusMessage::KEY, text('account.pin_changed'));
    }

    /**
     * Reset PIN-u: obecny PIN w polu ResetOwnPin::FIELD, potem PIN usunięty, wszystkie sesje i zaufane urządzenia
     * unieważnione, bieżąca sesja wylogowana; powrót do logowania z komunikatem o dalszych krokach.
     *
     * @throws ValidationException Gdy PIN jest błędny albo limit prób wyczerpany.
     */
    public function reset(Request $request, ResetOwnPin $reset, SignOut $signOut): RedirectResponse
    {
        $validated = $request->validate([ResetOwnPin::FIELD => PinRules::forEnteredPin()]);
        $pin = $validated[ResetOwnPin::FIELD];

        $reset->handle($this->account(), is_string($pin) ? $pin : '', $request);
        $signOut->handle($request);

        return $this->redirect->route($this->zone->get()->routeName(ZoneRedirects::LOGIN_ROUTE))->with(StatusMessage::KEY, text('account.pin_reset_done'));
    }

    private function account(): ZoneAccount
    {
        return $this->current->require();
    }

    private function back(): RedirectResponse
    {
        return SecurityController::redirectTo($this->redirect, $this->zone, AccountRoutes::SECTION_PIN);
    }
}
