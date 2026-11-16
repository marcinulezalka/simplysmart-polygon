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

namespace Simplysmart\Polygon\Auth;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Simplysmart\Polygon\Auth\Events\PinReset;
use Simplysmart\Polygon\Auth\SignIn\LoginAttemptLimiter;
use Simplysmart\Polygon\Auth\SignIn\PinCheckResult;
use Simplysmart\Polygon\Auth\SignIn\PinVerifier;
use Simplysmart\Polygon\Contracts\AccountStore;
use Simplysmart\Polygon\Contracts\CodeAndPinAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;

/**
 * Reset PIN-u przez właściciela zalogowanego konta (strona „Bezpieczeństwo”): po poprawnym obecnym PIN-ie (liczonym
 * do blokady jak przy logowaniu, wspólny limit ze zmianą PIN-u) usuwa PIN i unieważnia wszystkie sesje oraz zaufane
 * urządzenia konta, a właściciel dostaje potwierdzenie e-mailem (zdarzenie PinReset). Wylogowanie bieżącej sesji należy do kontrolera; przy następnym logowaniu kodem z e-maila konto
 * ustawia nowy PIN (krok PinSetupStep).
 */
final readonly class ResetOwnPin
{
    /** Pole formularza z obecnym PIN-em (inne niż `current_pin` zmiany PIN-u na tej samej stronie). */
    public const string FIELD = 'reset_pin';

    public function __construct(
        private LoginAttemptLimiter $limiter,
        private PinVerifier $pins,
        private AccountStore $store,
        private RevokeAccountAccess $revoke,
        private CurrentZone $zone,
        private Dispatcher $events,
    ) {}

    /**
     * @throws ValidationException Gdy limit jest wyczerpany albo obecny PIN jest błędny (lub konto zablokowane).
     */
    public function handle(Model&CodeAndPinAccount $account, string $currentPin, Request $request): void
    {
        $this->limiter->attempt(SignInLimit::PinChanges, (string) AccountKey::of($account), self::FIELD);

        if ($this->pins->verify($account, $currentPin, $request->ip()) !== PinCheckResult::Valid) {
            throw ValidationException::withMessages([self::FIELD => text('account.pin_invalid')]);
        }

        $zone = $this->zone->get();
        $this->store->clearPin($account);
        $this->revoke->handle($zone, $account, AccessRevocationReason::PinResetByOwner);

        $this->events->dispatch(new PinReset($zone->name, $account, $request->ip()));
    }
}
