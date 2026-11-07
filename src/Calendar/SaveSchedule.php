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
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceBooking;
use Simplysmart\Polygon\Models\ResourceSchedule;

/**
 * Zapis i usunięcie grafiku zasobu w transakcji: wiersz zasobu, a po nim wiersz wykonawcy są blokowane
 * (`FOR UPDATE`), więc dwa równoległe zapisy tego samego zasobu albo wykonawcy nie przejdą oba przez sprawdzenie kolizji
 * (ScheduleConflicts). Usunięcia grafiku z przyszłymi rezerwacjami (zapisanymi albo potwierdzonymi) nie ma — najpierw
 * trzeba je odwołać.
 */
final readonly class SaveSchedule
{
    public function __construct(
        private ConnectionInterface $db,
        private ScheduleConflicts $conflicts,
    ) {}

    /**
     * @throws ScheduleRejected Gdy grafik koliduje albo jest błędny.
     */
    public function save(ScheduleInput $input, ?ResourceSchedule $schedule = null): ResourceSchedule
    {
        return $this->db->transaction(function () use ($input, $schedule): ResourceSchedule {
            OrganizationResource::query()->whereKey($input->resourceId)->lockForUpdate()->first();
            $performer = $input->performer;

            if ($performer !== null) {
                $performer->newQuery()->whereKey($performer->id)->lockForUpdate()->first();
            }

            $conflicts = $this->conflicts->of($input, $schedule !== null && $schedule->exists ? $schedule->id : null);

            if ($conflicts !== []) {
                throw new ScheduleRejected($conflicts);
            }

            $schedule ??= new ResourceSchedule();
            $schedule->forceFill([
                ColumnNames::NAME => "{$input->startsAt}–{$input->endsAt}",
                ColumnNames::IS_ACTIVE => true,
                Organization::FOREIGN_KEY => $input->unitId,
                OrganizationResource::FOREIGN_KEY => $input->resourceId,
                ResourceSchedule::PERFORMER_TYPE => $input->performer?->getMorphClass(),
                ResourceSchedule::PERFORMER_ID => $input->performer?->id,
                ResourceSchedule::WEEKDAY => $input->weekday,
                ResourceSchedule::STARTS_AT => $input->startTime(),
                ResourceSchedule::ENDS_AT => $input->endTime(),
                ResourceSchedule::SLOT_MINUTES => $input->slotMinutes,
                ResourceSchedule::WEEK_INTERVAL => $input->weekInterval,
                ResourceSchedule::VALID_FROM => $input->validFrom->toDateString(),
                ResourceSchedule::VALID_TO => $input->validTo?->toDateString(),
            ])->save();

            return $schedule;
        });
    }

    /**
     * @throws ScheduleRejected Gdy w tym grafiku są przyszłe rezerwacje.
     */
    public function delete(ResourceSchedule $schedule): void
    {
        $this->db->transaction(function () use ($schedule): void {
            if ($this->futureBookings($schedule) > 0) {
                throw ScheduleRejected::because([PackageText::get('calendar.has_bookings')]);
            }

            $schedule->delete();
        });
    }

    /**
     * Przyszłe rezerwacje (zapisane albo potwierdzone) tego zasobu, wykonawcy i jednostki w dniu tygodnia i godzinach grafiku.
     */
    private function futureBookings(ResourceSchedule $schedule): int
    {
        $query = ResourceBooking::query();
        $query->where(Organization::FOREIGN_KEY, $schedule->organization_id);
        $query->where(OrganizationResource::FOREIGN_KEY, $schedule->resource_id);
        $query->where(ResourceSchedule::PERFORMER_TYPE, $schedule->performer_type);
        $query->where(ResourceSchedule::PERFORMER_ID, $schedule->performer_id);
        $query->where(ResourceBooking::STARTS_AT, '>=', CarbonImmutable::now());
        $query->whereIn(ColumnNames::STATUS, [BookingStatus::Booked->value, BookingStatus::Confirmed->value]);

        return $query->get()->filter(static fn(ResourceBooking $booking): bool => $schedule->appliesOn($booking->starts_at)
            && $booking->starts_at->format('H:i:s') >= $schedule->starts_at
            && $booking->starts_at->format('H:i:s') < $schedule->ends_at)->count();
    }
}
