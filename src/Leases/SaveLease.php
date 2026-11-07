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

namespace Simplysmart\Polygon\Leases;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\BookingStatus;
use Simplysmart\Polygon\Enums\LeaseAccess;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceBooking;
use Simplysmart\Polygon\Models\ResourceLease;
use Simplysmart\Polygon\Models\ResourceSchedule;

/**
 * Zapis, wypowiedzenie umowy najmu zasobu i nowy token tablicy, w transakcji z blokadą wiersza zasobu (`FOR UPDATE`) —
 * ta sama blokada co przy zapisie grafiku (Calendar\SaveSchedule), więc najem i grafik tego zasobu nie miną się
 * w sprawdzeniu kolizji (LeaseConflicts). Umowa z poziomem Wallboard dostaje losowy token publicznej tablicy.
 */
final readonly class SaveLease
{
    public function __construct(
        private ConnectionInterface $db,
        private LeaseConflicts $conflicts,
    ) {}

    /**
     * @throws LeaseRejected
     */
    public function save(LeaseInput $input, ?ResourceLease $lease = null): ResourceLease
    {
        return $this->db->transaction(function () use ($input, $lease): ResourceLease {
            $query = OrganizationResource::query();
            $query->whereKey($input->resourceId);
            $query->lockForUpdate();
            $resource = $query->first();
            $conflicts = $this->conflicts->of($input, $lease);

            if ($conflicts !== [] || ! $resource instanceof OrganizationResource) {
                throw new LeaseRejected($conflicts === [] ? [LeaseConflict::ResourceNotOwned] : $conflicts);
            }

            $lease ??= new ResourceLease();
            $lease->forceFill([
                ColumnNames::NAME => trim($input->number),
                ColumnNames::IS_ACTIVE => true,
                Organization::FOREIGN_KEY => $resource->organization_id,
                OrganizationResource::FOREIGN_KEY => $resource->id,
                ResourceLease::LESSEE_ID => $input->lessee->id,
                ResourceLease::COOPERATION_ID => $input->cooperation->id,
                ResourceLease::CONTRACT_ID => $input->contractId,
                ResourceLease::VALID_FROM => $input->validFrom->toDateString(),
                ResourceLease::VALID_TO => $input->validTo?->toDateString(),
                ResourceLease::WINDOWS => $input->windows->toArray(),
                ResourceLease::ACCESS => $input->accessValues(),
            ]);

            if ($lease->grants(LeaseAccess::Wallboard) && $lease->wallboard_token === null) {
                $lease->wallboard_token = self::token();
            }

            $lease->save();

            return $lease;
        });
    }

    /**
     * Wypowiedzenie: umowa obowiązuje do $lastDay włącznie (wypowiedziana przed początkiem — przestaje być aktywna),
     * grafik kooperacji na zasobie kończy się tego dnia (zaczynający się później przestaje być aktywny). Zwraca zapisy
     * (zapisane albo potwierdzone) kooperacji na zasobie po $lastDay — do odwołania.
     *
     * @return Collection<int, ResourceBooking>
     */
    public function terminate(ResourceLease $lease, CarbonImmutable $lastDay): Collection
    {
        return $this->db->transaction(function () use ($lease, $lastDay): Collection {
            OrganizationResource::query()->whereKey($lease->resource_id)->lockForUpdate()->first();
            $lastDay = $lastDay->startOfDay();

            if ($lastDay->lessThan($lease->valid_from->startOfDay())) {
                $lease->forceFill([ColumnNames::IS_ACTIVE => false])->save();
            } elseif ($lease->valid_to === null || $lease->valid_to->greaterThan($lastDay)) {
                $lease->forceFill([ResourceLease::VALID_TO => $lastDay->toDateString()])->save();
            }

            foreach ($this->cooperationSchedules($lease) as $schedule) {
                if ($schedule->valid_from->greaterThan($lastDay)) {
                    $schedule->forceFill([ColumnNames::IS_ACTIVE => false])->save();
                } elseif ($schedule->valid_to === null || $schedule->valid_to->greaterThan($lastDay)) {
                    $schedule->forceFill([ResourceSchedule::VALID_TO => $lastDay->toDateString()])->save();
                }
            }

            $query = ResourceBooking::query();
            $query->where(Organization::FOREIGN_KEY, $lease->cooperation_organization_id);
            $query->where(OrganizationResource::FOREIGN_KEY, $lease->resource_id);
            $query->where(ResourceBooking::STARTS_AT, '>=', $lastDay->addDay());
            $query->whereIn(ColumnNames::STATUS, [BookingStatus::Booked->value, BookingStatus::Confirmed->value]);
            $query->orderBy(ResourceBooking::STARTS_AT);

            return $query->get();
        });
    }

    /**
     * Nowy token publicznej tablicy (stary link przestaje działać).
     */
    public function renewWallboardToken(ResourceLease $lease): ResourceLease
    {
        $lease->forceFill([ResourceLease::WALLBOARD_TOKEN => self::token()])->save();

        return $lease;
    }

    private static function token(): string
    {
        return Str::random(ResourceLease::WALLBOARD_TOKEN_LENGTH);
    }

    /**
     * @return Collection<int, ResourceSchedule>
     */
    private function cooperationSchedules(ResourceLease $lease): Collection
    {
        $query = ResourceSchedule::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->where(Organization::FOREIGN_KEY, $lease->cooperation_organization_id);
        $query->where(OrganizationResource::FOREIGN_KEY, $lease->resource_id);

        return $query->get();
    }
}
