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

use Simplysmart\Polygon\Localization\PackageText;

/**
 * Stan rezerwacji w kalendarzu (kolumna `status` tabeli `resource_bookings`): zapisana, potwierdzona, wykonana, odwołana
 * albo nieobecność. Odwołana zwalnia termin; pozostałe go zajmują. Teksty: `enums.booking_status.<value>`.
 */
enum BookingStatus: string
{
    case Booked = 'booked';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Stany, które zajmują termin zasobu i wykonawcy.
     *
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [self::Booked, self::Confirmed, self::Completed, self::NoShow];
    }

    /**
     * Wartości stanów zajmujących termin (do zapytań).
     *
     * @return list<string>
     */
    public static function occupyingValues(): array
    {
        return array_map(static fn(self $status): string => $status->value, self::occupying());
    }

    /**
     * Czy ze stanu wolno przejść do $next: zapisana → potwierdzona, wykonana, odwołana, nieobecność; potwierdzona →
     * wykonana, odwołana, nieobecność; wykonana, odwołana i nieobecność są końcowe.
     */
    public function allows(self $next): bool
    {
        return match ($this) {
            self::Booked => $next !== self::Booked,
            self::Confirmed => in_array($next, [self::Completed, self::Cancelled, self::NoShow], true),
            self::Completed, self::Cancelled, self::NoShow => false,
        };
    }

    public function label(): string
    {
        return PackageText::get('enums.booking_status.' . $this->value);
    }
}
