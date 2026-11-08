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

use Carbon\CarbonImmutable;
use DomainException;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\BookingStatus;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\ResourceBooking;
use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Zmiana stanu rezerwacji według BookingStatus::allows(): potwierdzenie (zapisuje, kto i kiedy potwierdził), odwołanie
 * (zwalnia termin) i nieobecność.
 */
final readonly class BookingStatusChange
{
    /**
     * @throws DomainException Gdy przejście ze stanu rezerwacji jest niedozwolone.
     */
    public function change(ResourceBooking $booking, BookingStatus $next, ZoneAccount $by): void
    {
        if (! $booking->status->allows($next)) {
            throw new DomainException(PackageText::get('calendar.transition_rejected'));
        }

        $booking->forceFill([ColumnNames::STATUS => $next]);

        if ($next === BookingStatus::Confirmed) {
            $booking->forceFill(['confirmed_by_type' => $by->getMorphClass(), 'confirmed_by_id' => $by->id, ResourceBooking::CONFIRMED_AT => CarbonImmutable::now()]);
        }

        $booking->save();
    }
}
