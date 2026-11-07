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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\ContractorContract;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceLease;
use Simplysmart\Polygon\Models\ResourceSchedule;
use Simplysmart\Polygon\Queries\Leases\ResourceLeases;
use Simplysmart\Polygon\Queries\Organizations\OrganizationResources;
use Simplysmart\Polygon\Queries\Organizations\OrganizationUnits;

/**
 * Kolizje umowy najmu przed zapisem (SaveLease): poprawny okres, okna i numer; zasób właściciela; najemca poza drzewem
 * właściciela; kooperacja z flagą, aktywna i podrzędna najemcy; umowa kontrahenta właściciela; bez nakładania się okien
 * z innym aktywnym najmem zasobu w pokrywającym się okresie; bez aktywnego grafiku innej jednostki na zasobie w oknach
 * i okresie; przy zmianie umowy — bez grafiku kooperacji poza nowymi oknami albo okresem.
 */
final readonly class LeaseConflicts
{
    public function __construct(
        private OrganizationResources $resources,
        private OrganizationUnits $units,
        private ResourceLeases $leases,
    ) {}

    /**
     * @return list<LeaseConflict>
     */
    public function of(LeaseInput $input, ?ResourceLease $lease = null): array
    {
        $conflicts = [];
        $ignoreId = $lease !== null && $lease->exists ? $lease->id : null;

        if (trim($input->number) === '' || ! $input->windows->isValid() || ($input->validTo !== null && $input->validTo->lessThan($input->validFrom))) {
            $conflicts[] = LeaseConflict::InvalidRange;
        }

        $owned = $this->resources->owns($input->owner, $input->resourceId);

        if (! $owned) {
            $conflicts[] = LeaseConflict::ResourceNotOwned;
        }

        $owner = $owned ? OrganizationResource::query()->whereKey($input->resourceId)->value(Organization::FOREIGN_KEY) : null;
        $ownerId = is_numeric($owner) ? (int) $owner : $input->owner->id;

        if (in_array($ownerId, $this->units->lineage($input->lessee), true) || in_array($input->lessee->id, $this->units->lineage($input->owner), true)) {
            $conflicts[] = LeaseConflict::LesseeInOwnerTree;
        }

        if (! $input->cooperation->isCooperation() || ! $input->cooperation->is_active || $input->cooperation->parent_id !== $input->lessee->id) {
            $conflicts[] = LeaseConflict::NotCooperation;
        }

        if ($input->contractId !== null && ! $this->contractOfOwner($input)) {
            $conflicts[] = LeaseConflict::ContractUnavailable;
        }

        if ($conflicts !== []) {
            return $conflicts;
        }

        foreach ($this->leases->overlapping($input->resourceId, $input->validFrom, $input->validTo, $ignoreId) as $other) {
            if ($other->leaseWindows()->overlapsWindows($input->windows)) {
                $conflicts[] = LeaseConflict::Overlapping;

                break;
            }
        }

        $schedules = $this->schedules($input);

        if ($schedules->contains(static fn(ResourceSchedule $schedule): bool => $schedule->organization_id !== $input->cooperation->id && $input->windows->overlaps($schedule->weekday, $schedule->from(), $schedule->to()))) {
            $conflicts[] = LeaseConflict::ResourceScheduled;
        }

        if ($ignoreId !== null && $this->cooperationOutside($input)) {
            $conflicts[] = LeaseConflict::ScheduleOutside;
        }

        return $conflicts;
    }

    private function contractOfOwner(LeaseInput $input): bool
    {
        $contract = ContractorContract::query()->with('contractor')->find($input->contractId);
        $contractor = $contract?->contractor;

        return $contractor instanceof Contractor && in_array($contractor->organization_id, $this->units->lineage($input->owner), true);
    }

    /**
     * Aktywny grafik zasobu nachodzący na okres umowy.
     *
     * @return Collection<int, ResourceSchedule>
     */
    private function schedules(LeaseInput $input): Collection
    {
        $query = ResourceSchedule::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->where(OrganizationResource::FOREIGN_KEY, $input->resourceId);
        $query->where(static function (Builder $period) use ($input): void {
            $period->whereNull(ResourceSchedule::VALID_TO)->orWhereDate(ResourceSchedule::VALID_TO, '>=', $input->validFrom);
        });

        if ($input->validTo !== null) {
            $query->whereDate(ResourceSchedule::VALID_FROM, '<=', $input->validTo);
        }

        return $query->get();
    }

    /**
     * Czy aktywny grafik kooperacji na zasobie wychodzi poza nowe okna albo okres umowy.
     */
    private function cooperationOutside(LeaseInput $input): bool
    {
        $query = ResourceSchedule::query();
        $query->where(ColumnNames::IS_ACTIVE, true);
        $query->where(OrganizationResource::FOREIGN_KEY, $input->resourceId);
        $query->where(Organization::FOREIGN_KEY, $input->cooperation->id);

        return $query->get()->contains(static function (ResourceSchedule $schedule) use ($input): bool {
            $periodOutside = $schedule->valid_from->lessThan($input->validFrom)
                || ($input->validTo !== null && ($schedule->valid_to === null || $schedule->valid_to->greaterThan($input->validTo)));

            return $periodOutside || ! $input->windows->covers($schedule->weekday, $schedule->from(), $schedule->to());
        });
    }
}
