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

use Illuminate\Contracts\Session\Session;
use Simplysmart\Polygon\Contracts\RevocableAccess;

/**
 * Epoka konta zapisana w sesji: sesja jest ważna, dopóki zgadza się z epoką konta (zmiana epoki unieważnia wszystkie
 * sesje konta — Http\Middleware\EnsureAccountCanSignIn). Po zalogowaniu i po zmianie PIN-u bieżąca sesja dostaje nowy
 * identyfikator i aktualną epokę.
 */
final class SessionEpoch
{
    public const string KEY = 'auth_epoch';

    public static function renew(Session $session, RevocableAccess $account): void
    {
        $session->regenerate();
        $session->put(self::KEY, $account->authEpoch());
    }

    public static function matches(Session $session, RevocableAccess $account): bool
    {
        return $session->get(self::KEY) === $account->authEpoch();
    }
}
