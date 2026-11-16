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
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;
use Simplysmart\Polygon\Auth\PinRules;
use Simplysmart\Polygon\Auth\SignIn\PendingSignIn;
use Simplysmart\Polygon\Auth\SignIn\PendingSignInResolver;
use Simplysmart\Polygon\Contracts\AccountStore;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Contracts\SignInStep;

/**
 * Ustawienie PIN-u przy pierwszym logowaniu (konto bez PIN-u, po kroku kodu).
 */
final readonly class PinSetupStep implements SignInStep
{
    public const string KEY = 'pin-setup';

    public function __construct(
        private Hasher $hasher,
        private AccountStore $store,
        private PendingSignInResolver $pending,
        private CurrentZone $zone,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function isRequired(PendingSignIn $signIn): bool
    {
        return $signIn->account !== null && ! $signIn->account->hasPin();
    }

    public function ttlMinutes(): int
    {
        return $this->zone->get()->auth()->pinStageTtlMinutes;
    }

    public function view(): ZoneView
    {
        return ZoneView::PinSetup;
    }

    public function viewData(PendingSignIn $signIn): array
    {
        return ['pinLength' => $this->zone->get()->auth()->pinLength];
    }

    public function rules(): array
    {
        return ['pin' => PinRules::forNewPin($this->zone->get()->auth()->pinLength)];
    }

    public function handle(PendingSignIn $signIn, array $input, Request $request): void
    {
        $account = $signIn->account;

        if ($account === null || $account->hasPin()) {
            $this->pending->abort();
        }

        $this->store->setPinHash($account, $this->hasher->make(is_string($input['pin'] ?? null) ? $input['pin'] : ''));
    }
}
