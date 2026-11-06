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

namespace Simplysmart\Polygon\Http\Controllers\Auth;

use Simplysmart\Polygon\Http\StatusMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Simplysmart\Polygon\Auth\SignIn\SignOut;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Zones\ZoneRedirects;

/**
 * Wylogowanie z bieżącej strefy i powrót do jej logowania z komunikatem „Wylogowano”.
 */
final readonly class LogoutController
{
    public function __invoke(Request $request, SignOut $signOut, CurrentZone $zone, Redirector $redirect): RedirectResponse
    {
        $signOut->handle($request);

        return $redirect->route($zone->get()->routeName(ZoneRedirects::LOGIN_ROUTE))->with(StatusMessage::KEY, text('sign_in.signed_out'));
    }
}
