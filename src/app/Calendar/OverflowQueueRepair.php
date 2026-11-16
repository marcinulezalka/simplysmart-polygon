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

/**
 * Nadaje własne godziny rezerwacjom nadliczbowym zapisanym przed kolejką nadliczbowych (bez
 * ResourceBooking::OVERFLOW_AFTER — wszystkie na samym końcu bloku, z tą samą godziną): w kolejności zapisu dostają
 * kolejne wolne miejsca kolejki bloku (ResourceSchedule::nextOverflowAt()), na jeden slot — jak z BookSlot::bookOverflow().
 * Rezerwacje już w kolejce zostają na swoich miejscach; rezerwacja, której bloku grafiku już nie ma, zostaje bez zmian.
 */
final readonly class OverflowQueueRepair
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    /**
     * Naprawia rezerwacje nadliczbowe zajmujące termin od dnia $from.
     *
     * @return array{repaired: int, skipped: int} Rezerwacje z nową godziną i pominięte (bez bloku grafiku).
     */
    public function from(CarbonImmutable $from): array
    {
        return $this->db->transaction(function () use ($from): array {
            $result = ['repaired' => 0, 'skipped' => 0];

            foreach ($this->queues($from) as $bookings) {
                $legacy = array_values(array_filter($bookings, static fn(ResourceBooking $booking): bool => ! $booking->hasOverflowPlace()));

                if ($legacy === []) {
                    continue;
                }

                $schedule = self::schedule($legacy[0]);

                if ($schedule === null) {
                    $result['skipped'] += count($legacy);

                    continue;
                }

                $placed = array_values(array_filter($bookings, static fn(ResourceBooking $booking): bool => $booking->hasOverflowPlace()));

                foreach ($legacy as $booking) {
                    $booking->placeOverflow($schedule, $schedule->nextOverflowAt($booking->starts_at, $placed))->save();
                    $placed[] = $booking;
                    $result['repaired']++;
                }
            }

            return $result;
        });
    }

    /**
     * Rezerwacje nadliczbowe zajmujące termin od dnia $from (zablokowane do końca transakcji), w kolejności zapisu,
     * pogrupowane w kolejki bloków.
     *
     * @return array<string, list<ResourceBooking>>
     */
    private function queues(CarbonImmutable $from): array
    {
        $query = ResourceBooking::query();
        $query->whereIn(ColumnNames::STATUS, BookingStatus::occupyingValues());
        $query->where(ResourceBooking::STARTS_AT, '>=', $from->startOfDay());
        $query->orderBy(ColumnNames::ID);
        $query->lockForUpdate();
        $queues = [];

        foreach ($query->get() as $booking) {
            if ($booking->isOverflow()) {
                $queues[implode('|', [$booking->organization_id, $booking->resource_id, (string) $booking->performer_type, (string) $booking->performer_id, $booking->starts_at->format('Y-m-d'), (string) $booking->overflowAfter()])][] = $booking;
            }
        }

        return $queues;
    }

    /**
     * Blok grafiku ze slotami, po którym stoi rezerwacja: ta sama jednostka, zasób i wykonawca, obowiązujący w jej dniu
     * i kończący się o ResourceBooking::overflowAfter().
     */
    private static function schedule(ResourceBooking $booking): ?ResourceSchedule
    {
        $query = ResourceSchedule::query();
        $query->where(Organization::FOREIGN_KEY, $booking->organization_id);
        $query->where(OrganizationResource::FOREIGN_KEY, $booking->resource_id);
        $query->where(ResourceSchedule::PERFORMER_TYPE, $booking->performer_type);
        $query->where(ResourceSchedule::PERFORMER_ID, $booking->performer_id);
        $query->where(ResourceSchedule::WEEKDAY, $booking->starts_at->dayOfWeekIso);
        $query->where(ResourceSchedule::FILLS_GAPS, false);
        $query->where(ResourceSchedule::SLOT_MINUTES, '>', 0);

        return $query->get()->first(static fn(ResourceSchedule $schedule): bool => $schedule->appliesOn($booking->starts_at) && $schedule->holds($booking));
    }
}
