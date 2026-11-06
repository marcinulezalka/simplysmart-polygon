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

namespace Simplysmart\Polygon\Zones;

/**
 * Ekrany logowania i bezpieczeństwa konta, które dostarcza paczka strefy (polygon nie ma widoków): config strefy
 * `views` mapuje każdy ekran na nazwę widoku, np. `'login' => 'shop::auth.login'`.
 */
enum ZoneView: string
{
    /** Formularz adresu e-mail (początek logowania). */
    case Login = 'login';

    /** Kod z e-maila. */
    case Code = 'code';

    /** PIN konta. */
    case Pin = 'pin';

    /** Ustawienie PIN-u przy pierwszym logowaniu. */
    case PinSetup = 'pin_setup';

    /** Reset zapomnianego PIN-u kodem wysłanym na zapasowy adres. */
    case ForgotPin = 'forgot_pin';

    /** Odblokowanie ekranu PIN-em. */
    case Unlock = 'unlock';

    /** Bezpieczeństwo konta: zmiana PIN-u, zapasowy e-mail, zaufane urządzenia. */
    case Security = 'security';
}
