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

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Simplysmart\Polygon\Auth\SignIn\SignInFlowNavigator;
use Simplysmart\Polygon\Auth\SignIn\StartSignIn;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Zones\ZoneView;

/**
 * Formularz adresu e-mail (początek logowania strefy, ekran ZoneView::Login strefy).
 */
final readonly class LoginController
{
    public function __construct(
        private CurrentZone $zone,
    ) {}

    public function create(): View
    {
        return view($this->zone->get()->view(ZoneView::Login), ['zone' => $this->zone->get()]);
    }

    public function store(Request $request, StartSignIn $start, SignInFlowNavigator $navigator): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255']]);

        $start->handle(is_string($validated['email']) ? $validated['email'] : '', $request);

        return $navigator->continue($request);
    }
}
