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

namespace Simplysmart\Polygon\Auth\SignIn\Steps;

use Simplysmart\Polygon\Zones\ZoneView;
use Illuminate\Http\Request;
use Simplysmart\Polygon\Auth\PinLength;
use Simplysmart\Polygon\Auth\PinRules;
use Simplysmart\Polygon\Auth\SignIn\LoginAttemptLimiter;
use Simplysmart\Polygon\Auth\SignIn\PendingSignIn;
use Simplysmart\Polygon\Auth\SignIn\PendingSignInResolver;
use Simplysmart\Polygon\Auth\SignIn\PinCheckResult;
use Simplysmart\Polygon\Auth\SignIn\PinVerifier;
use Simplysmart\Polygon\Auth\SignIn\SignInFailure;
use Simplysmart\Polygon\Auth\SignInLimit;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Contracts\SignInStep;

/**
 * PIN (wiedza). Zablokowane konto wraca do początku z ogólnym komunikatem; o blokadzie
 * dowiaduje się tylko właściciel (e-mail).
 */
final readonly class PinStep implements SignInStep
{
    public const string KEY = 'pin';

    public function __construct(
        private PinVerifier $pins,
        private LoginAttemptLimiter $limiter,
        private PendingSignInResolver $pending,
        private CurrentZone $zone,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function isRequired(PendingSignIn $signIn): bool
    {
        return $signIn->account === null || ($signIn->account->hasPin() && ! $signIn->hasCompleted(PinSetupStep::KEY));
    }

    public function ttlMinutes(): int
    {
        return $this->zone->get()->auth()->pinStageTtlMinutes;
    }

    public function view(): ZoneView
    {
        return ZoneView::Pin;
    }

    public function viewData(PendingSignIn $signIn): array
    {
        return ['pinLength' => PinLength::accepted()];
    }

    public function rules(): array
    {
        return ['pin' => PinRules::forEnteredPin()];
    }

    public function handle(PendingSignIn $signIn, array $input, Request $request): void
    {
        $this->limiter->attempt(SignInLimit::PinChecks, (string) $request->ip(), 'pin');

        $account = $signIn->account ?? $this->pending->abort();
        $pin = is_string($input['pin'] ?? null) ? $input['pin'] : '';

        match ($this->pins->verify($account, $pin, $request->ip())) {
            PinCheckResult::Valid => null,
            PinCheckResult::Invalid => throw SignInFailure::on('pin'),
            PinCheckResult::Locked => $this->pending->abort(),
        };
    }
}
