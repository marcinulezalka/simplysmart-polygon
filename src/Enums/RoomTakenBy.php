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

namespace Simplysmart\Polygon\Enums;

/**
 * Powód niedostępności zasobu tuż po bloku grafiku (Calendar\RoomTaken, FreeSlots::roomTakenAfter()).
 */
enum RoomTakenBy: string
{
    /** Inny grafik na tym zasobie (np. inny lekarz w tym gabinecie). */
    case Schedule = 'schedule';

    /** Okno najmu na wyłączność innej jednostki. */
    case Lease = 'lease';

    /** Zasób wynajęty jednostce bloku, ale jej najem po bloku nie obowiązuje. */
    case NoLease = 'no_lease';

    /** Rezerwacja innego wykonawcy na tym zasobie. */
    case Booking = 'booking';
}
