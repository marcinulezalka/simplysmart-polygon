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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use Simplysmart\Polygon\Auth\ChangePin;
use Simplysmart\Polygon\Auth\DefinePin;
use Simplysmart\Polygon\Auth\PinRules;
use Simplysmart\Polygon\Auth\ResetOwnPin;
use Simplysmart\Polygon\Auth\SignIn\IssueSignInCode;
use Simplysmart\Polygon\Auth\SignIn\LoginAttemptLimiter;
use Simplysmart\Polygon\Auth\SignIn\PinCheckResult;
use Simplysmart\Polygon\Auth\SignIn\PinVerifier;
use Simplysmart\Polygon\Auth\SignIn\SignOut;
use Simplysmart\Polygon\Auth\SignInLimit;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Http\AccountRoutes;
use Simplysmart\Polygon\Http\SecurityPage;
use Simplysmart\Polygon\Http\StatusMessage;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Zones\ZoneRedirects;

/**
 * Zmiana PIN-u zalogowanego konta (formularz na stronie bezpieczeństwa albo własnej stronie sekcji, Http\SecurityPage):
 * wysłanie kodu na adres konta po poprawnym obecnym PIN-ie (żądanie fetch dostaje JSON), zapis nowego PIN-u, pierwszy PIN konta bez PIN-u (define()) albo reset
 * PIN-u obecnym PIN-em z wylogowaniem (nowy PIN przy następnym logowaniu).
 */
final readonly class PinChangeController
{
    private const string CURRENT_PIN = 'current_pin';

    public function __construct(
        private CurrentZone $zone,
        private CurrentAccount $current,
        private Redirector $redirect,
        private SecurityPage $page,
    ) {}

    /**
     * @throws ValidationException Gdy obecny PIN jest błędny albo limit kodów wyczerpany, a żądanie oczekuje JSON.
     */
    public function sendCode(Request $request, PinVerifier $pins, LoginAttemptLimiter $limiter, Dispatcher $bus): RedirectResponse|JsonResponse
    {
        try {
            $this->issueCode($request, $pins, $limiter, $bus);
        } catch (ValidationException $rejected) {
            return $this->rejected($request, $rejected);
        }

        return $request->expectsJson()
            ? new JsonResponse(['message' => text('account.code_sent')])
            : $this->back()->with(StatusMessage::KEY, text('account.code_sent'));
    }

    /**
     * @throws ValidationException Gdy pola są błędne, a żądanie oczekuje JSON.
     */
    public function update(Request $request, ChangePin $change): RedirectResponse
    {
        try {
            $this->changePin($request, $change);
        } catch (ValidationException $rejected) {
            return $this->rejected($request, $rejected);
        }

        return $this->back()->with(StatusMessage::KEY, text('account.pin_changed'));
    }

    /**
     * Pierwszy PIN konta zalogowanego bez PIN-u; potem strona startowa konta.
     *
     * @throws ValidationException Gdy PIN jest błędny, a żądanie oczekuje JSON.
     */
    public function define(Request $request, DefinePin $define, ZoneRedirects $redirects): RedirectResponse
    {
        $account = $this->account();

        try {
            $validated = $request->validate(['pin' => PinRules::forNewPin($this->zone->get()->auth()->pinLength)]);
        } catch (ValidationException $rejected) {
            return $this->rejected($request, $rejected);
        }

        $define->handle($account, self::entered($validated, 'pin'), $request);

        return $this->redirect->to($redirects->homeFor($this->zone->get(), $account))->with(StatusMessage::KEY, text('account.pin_defined'));
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

        $reset->handle($this->account(), self::entered($validated, ResetOwnPin::FIELD), $request);
        $signOut->handle($request);

        return $this->redirect->route($this->zone->get()->routeName(ZoneRedirects::LOGIN_ROUTE))->with(StatusMessage::KEY, text('account.pin_reset_done'));
    }

    /**
     * @throws ValidationException Gdy obecny PIN jest błędny albo limit kodów wyczerpany.
     */
    private function issueCode(Request $request, PinVerifier $pins, LoginAttemptLimiter $limiter, Dispatcher $bus): void
    {
        $account = $this->account();
        $validated = $request->validate([self::CURRENT_PIN => PinRules::forEnteredPin()]);

        if ($pins->verify($account, self::entered($validated, self::CURRENT_PIN), $request->ip()) !== PinCheckResult::Valid) {
            throw ValidationException::withMessages([self::CURRENT_PIN => text('account.pin_invalid')]);
        }

        $limiter->attempt(SignInLimit::AccountCodeRequests, $account->emailAddress(), 'code');
        $bus->dispatch(new IssueSignInCode($this->zone->get()->name, $account->emailAddress(), $request->ip(), $request->userAgent()));
    }

    /**
     * @throws ValidationException Gdy pola są błędne albo zmiana jest odrzucona (Auth\ChangePin).
     */
    private function changePin(Request $request, ChangePin $change): void
    {
        $settings = $this->zone->get()->auth();
        $validated = $request->validate([
            self::CURRENT_PIN => PinRules::forEnteredPin(),
            'code' => ['required', 'string', 'digits:' . $settings->codeLength],
            'pin' => PinRules::forNewPin($settings->pinLength),
        ]);

        $change->handle(
            $this->account(),
            self::entered($validated, self::CURRENT_PIN),
            self::entered($validated, 'code'),
            self::entered($validated, 'pin'),
            $request,
        );
    }

    /**
     * Odrzucone żądanie: 422 dla fetch, a z formularza powrót do sekcji z błędami — bez wpisanych PIN-ów i kodu w sesji.
     *
     * @throws ValidationException
     */
    private function rejected(Request $request, ValidationException $rejected): RedirectResponse
    {
        if ($request->expectsJson()) {
            throw $rejected;
        }

        return $this->back()->withErrors($rejected->errors());
    }

    /**
     * @param array<array-key, mixed> $validated
     */
    private static function entered(array $validated, string $field): string
    {
        $value = $validated[$field] ?? null;

        return is_string($value) ? $value : '';
    }

    private function account(): ZoneAccount
    {
        return $this->current->require();
    }

    private function back(): RedirectResponse
    {
        return $this->page->redirect($this->zone->get(), AccountRoutes::SECTION_PIN);
    }
}
