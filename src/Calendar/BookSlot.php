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
use Illuminate\Database\ConnectionInterface;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\BookingStatus;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceBooking;
use Simplysmart\Polygon\Models\ResourceSchedule;
use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Rezerwacja slotu wykonawcy w jednostce (stan Booked, rodzaj $kind z paczki strefy): w transakcji blokuje
 * (`FOR UPDATE`) wiersz zasobu z grafiku, a po nim wiersz wykonawcy — w tej kolejności, bez zakleszczeń — i dopiero
 * wtedy sprawdza, że termin jest wolnym slotem (FreeSlots). Równoległa rezerwacja tego zasobu albo wykonawcy czeka na
 * blokadę, a potem widzi zajęty slot i kończy się SlotUnavailable. Wywołana w transakcji paczki strefy (np. razem
 * z danymi osoby) — staje się jej częścią.
 */
final readonly class BookSlot
{
    public function __construct(
        private ConnectionInterface $db,
        private FreeSlots $slots,
    ) {}

    /**
     * @throws SlotUnavailable Gdy termin nie jest (już) wolny.
     */
    public function book(Organization $unit, ZoneAccount $performer, CarbonImmutable $startsAt, string $kind, ?ZoneAccount $bookedBy = null): ResourceBooking
    {
        return $this->db->transaction(function () use ($unit, $performer, $startsAt, $kind, $bookedBy): ResourceBooking {
            $schedule = $this->slots->scheduleAt($unit, $performer, $startsAt) ?? throw new SlotUnavailable();
            OrganizationResource::query()->whereKey($schedule->resource_id)->lockForUpdate()->first();
            $performer->newQuery()->whereKey($performer->id)->lockForUpdate()->first();
            $slot = $this->slots->at($unit, $performer, $startsAt) ?? throw new SlotUnavailable();

            $booking = new ResourceBooking();
            $booking->forceFill([
                ColumnNames::NAME => $slot->startsAt->format('Y-m-d H:i'),
                ColumnNames::STATUS => BookingStatus::Booked,
                Organization::FOREIGN_KEY => $unit->id,
                OrganizationResource::FOREIGN_KEY => $slot->resourceId,
                ResourceSchedule::PERFORMER_TYPE => $performer->getMorphClass(),
                ResourceSchedule::PERFORMER_ID => $performer->id,
                ResourceBooking::KIND => $kind,
                ResourceBooking::STARTS_AT => $slot->startsAt,
                ResourceBooking::ENDS_AT => $slot->endsAt,
                'booked_by_type' => $bookedBy?->getMorphClass(),
                'booked_by_id' => $bookedBy?->id,
            ])->save();

            return $booking;
        });
    }

    /**
     * Rezerwacja nadliczbowa: ponad wolne sloty, na końcu bloku grafiku wykonawcy kończącego się o $endsAt
     * (FreeSlots::scheduleEndingAt()). Ma zerową długość (`starts_at` = `ends_at` = koniec bloku), więc nie zajmuje
     * żadnego slotu ani nie koliduje z następnym blokiem; `json_attributes.overflow` (ResourceBooking::OVERFLOW) ją
     * odróżnia. Blokady jak przy book(). Czy wykonawca przyjmuje nadliczbowo — decyduje paczka strefy przed wywołaniem.
     *
     * @throws SlotUnavailable Gdy żaden blok grafiku nie kończy się o $endsAt albo ten koniec już minął.
     */
    public function bookOverflow(Organization $unit, ZoneAccount $performer, CarbonImmutable $endsAt, string $kind, ?ZoneAccount $bookedBy = null): ResourceBooking
    {
        return $this->db->transaction(function () use ($unit, $performer, $endsAt, $kind, $bookedBy): ResourceBooking {
            $schedule = $this->slots->scheduleEndingAt($unit, $performer, $endsAt);

            if ($schedule === null || $endsAt->lessThanOrEqualTo(CarbonImmutable::now())) {
                throw new SlotUnavailable();
            }

            OrganizationResource::query()->whereKey($schedule->resource_id)->lockForUpdate()->first();
            $performer->newQuery()->whereKey($performer->id)->lockForUpdate()->first();

            $booking = new ResourceBooking();
            $booking->forceFill([
                ColumnNames::NAME => $endsAt->format('Y-m-d H:i'),
                ColumnNames::STATUS => BookingStatus::Booked,
                Organization::FOREIGN_KEY => $unit->id,
                OrganizationResource::FOREIGN_KEY => $schedule->resource_id,
                ResourceSchedule::PERFORMER_TYPE => $performer->getMorphClass(),
                ResourceSchedule::PERFORMER_ID => $performer->id,
                ResourceBooking::KIND => $kind,
                ResourceBooking::STARTS_AT => $endsAt,
                ResourceBooking::ENDS_AT => $endsAt,
                'booked_by_type' => $bookedBy?->getMorphClass(),
                'booked_by_id' => $bookedBy?->id,
                ColumnNames::JSON_ATTRIBUTES => [ResourceBooking::OVERFLOW => true],
            ])->save();

            return $booking;
        });
    }
}
