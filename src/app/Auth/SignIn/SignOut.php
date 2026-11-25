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

use Simplysmart\Polygon\Auth\AccountKey;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Simplysmart\Polygon\Auth\Events\SignedOut;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Organizations\LastContext;

/**
 * Wylogowanie z bieżącej strefy: unieważnia tylko jej sesję (inne strefy zostają zalogowane) i usuwa ostatni kontekst
 * konta (Organizations\LastContext) — następne logowanie zaczyna od kontekstu domyślnego.
 */
final readonly class SignOut
{
    public function __construct(
        private AuthManager $auth,
        private CurrentZone $zone,
        private Dispatcher $events,
        private LastContext $lastContext,
    ) {}

    public function handle(Request $request): void
    {
        $zone = $this->zone->get();
        $guard = $this->auth->guard($zone->guard);
        $accountId = $guard->id();
        $account = $guard->user();

        if ($account instanceof ZoneAccount) {
            $this->lastContext->forget($account);
        }

        if ($guard instanceof StatefulGuard) {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->events->dispatch(new SignedOut($zone->name, AccountKey::from($accountId)));
    }
}
