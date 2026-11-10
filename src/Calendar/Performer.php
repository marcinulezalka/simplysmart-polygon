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

namespace Simplysmart\Polygon\Calendar;

use Simplysmart\Polygon\Contracts\SignInAccount;
use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Przyjmujący (wykonawca) bloku grafiku albo rezerwacji: relacja polimorficzna `performer` (ResourceSchedule,
 * ResourceBooking), więc konto i nazwę odczytuje się tylko tutaj — modele udostępniają je jako performerAccount()
 * i performerName().
 */
final class Performer
{
    /**
     * Konto strefy przyjmującego albo null (brak wykonawcy albo inny model).
     */
    public static function account(mixed $performer): ?ZoneAccount
    {
        return $performer instanceof ZoneAccount ? $performer : null;
    }

    /**
     * Nazwa przyjmującego do wyświetlenia (SignInAccount::displayName()); bez wykonawcy — pusty tekst.
     */
    public static function name(mixed $performer): string
    {
        return $performer instanceof SignInAccount ? $performer->displayName() : '';
    }
}
