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

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Simplysmart\Polygon\Auth\Devices\RegisterTrustedDevice;
use Simplysmart\Polygon\Contracts\AccountStore;
use Simplysmart\Polygon\Contracts\CodeAndPinAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Pierwszy PIN zalogowanego konta (strefa z ZoneAuthSettings::definesPinInAccount()). Konto, które ma już PIN, zmienia go
 * tylko przez ChangePin. Ustawienie unieważnia pozostałe sesje konta (zalogowane samym kodem, bez znajomości PIN-u);
 * bieżąca zostaje. Urządzenie, które konto chciało zapamiętać przy logowaniu, staje się zaufane dopiero teraz — zaufane
 * urządzenie konta bez PIN-u pozwalałoby logować się bez kodu i bez PIN-u.
 */
final readonly class DefinePin
{
    /** Klucz sesji: konto poprosiło przy logowaniu o zapamiętanie urządzenia (CompleteSignIn). */
    public const string REMEMBER_DEVICE_SESSION_KEY = 'remember_device_after_pin';

    public function __construct(
        private MissingPin $missing,
        private Hasher $hasher,
        private AccountStore $store,
        private RevokeAccountAccess $revoke,
        private RegisterTrustedDevice $devices,
        private CurrentZone $zone,
    ) {}

    /**
     * @throws AccessDeniedHttpException Gdy konto ma już PIN albo strefa nie definiuje go po zalogowaniu.
     */
    public function handle(Model&CodeAndPinAccount $account, string $pin, Request $request): void
    {
        $zone = $this->zone->get();

        if (! $this->missing->of($zone, $account)) {
            throw new AccessDeniedHttpException();
        }

        $this->store->setPinHash($account, $this->hasher->make($pin));
        $this->revoke->handle($zone, $account, AccessRevocationReason::PinChanged);

        SessionEpoch::renew($request->session(), $account);

        if ($request->session()->pull(self::REMEMBER_DEVICE_SESSION_KEY) === true && $zone->auth()->trustedDevicesEnabled) {
            $this->devices->handle($account, $request);
        }
    }
}
