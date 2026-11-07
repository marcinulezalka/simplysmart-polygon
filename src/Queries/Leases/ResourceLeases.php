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

namespace Simplysmart\Polygon\Queries\Leases;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\LeaseAccess;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceLease;
use Simplysmart\Polygon\Queries\Organizations\OrganizationUnits;

/**
 * Odczyt umów najmu zasobów: najmy właściciela (z drzewa organizacji), aktywne najmy zasobu w okresie, najmy jednostki
 * kooperacji, kanały właściciela (jednostki kooperacji z danym poziomem udostępnienia, obowiązujące dziś albo
 * później) i najem po tokenie tablicy.
 */
final readonly class ResourceLeases
{
    public function __construct(
        private OrganizationUnits $units,
    ) {}

    /**
     * Najmy zasobów organizacji i jej przodków (lista umów właściciela), od najnowszej.
     *
     * @return Collection<int, ResourceLease>
     */
    public function ofOwner(Organization $owner): Collection
    {
        $query = ResourceLease::query();
        $query->whereIn(Organization::FOREIGN_KEY, $this->units->lineage($owner));
        $query->with(['resource', 'lessee', 'cooperation', 'contract']);
        $query->orderByDesc(ResourceLease::VALID_FROM);
        $query->orderByDesc(ColumnNames::ID);

        return $query->get();
    }

    public function findOfOwner(Organization $owner, string $token): ?ResourceLease
    {
        $query = ResourceLease::query();
        $query->whereIn(Organization::FOREIGN_KEY, $this->units->lineage($owner));
        $query->where(ColumnNames::TOKEN, $token);

        return $query->first();
    }

    /**
     * Aktywne najmy zasobu nachodzące na okres $from–$to (bez końca: null), bez $ignoreId.
     *
     * @return Collection<int, ResourceLease>
     */
    public function overlapping(int $resourceId, CarbonImmutable $from, ?CarbonImmutable $to, ?int $ignoreId = null): Collection
    {
        $query = $this->active();
        $query->where(OrganizationResource::FOREIGN_KEY, $resourceId);
        $query->where(static function (Builder $period) use ($from): void {
            $period->whereNull(ResourceLease::VALID_TO)->orWhereDate(ResourceLease::VALID_TO, '>=', $from);
        });

        if ($to !== null) {
            $query->whereDate(ResourceLease::VALID_FROM, '<=', $to);
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->get();
    }

    /**
     * Aktywne najmy zasobu, w których jednostka (albo jej przodek) jest kooperacją najemcy.
     *
     * @return Collection<int, ResourceLease>
     */
    public function ofCooperation(Organization $unit, int $resourceId): Collection
    {
        $query = $this->active();
        $query->where(OrganizationResource::FOREIGN_KEY, $resourceId);
        $query->whereIn(ResourceLease::COOPERATION_ID, $this->units->lineage($unit));

        return $query->get();
    }

    /**
     * Id zasobów wynajętych jednostce (albo jej przodkowi) w umowach obowiązujących dziś albo później.
     *
     * @return list<int>
     */
    public function leasedResourceIds(Organization $unit): array
    {
        $query = $this->current();
        $query->whereIn(ResourceLease::COOPERATION_ID, $this->units->lineage($unit));

        return array_values(array_unique(array_map(static fn(ResourceLease $lease): int => $lease->resource_id, $query->get([OrganizationResource::FOREIGN_KEY])->all())));
    }

    /**
     * Kanały właściciela: najmy jego zasobów (z drzewa organizacji) obowiązujące dziś albo później, dające poziom $access,
     * z jednostką kooperacji, najemcą i zasobem.
     *
     * @return Collection<int, ResourceLease>
     */
    public function channels(Organization $owner, LeaseAccess $access): Collection
    {
        $query = $this->current();
        $query->whereIn(Organization::FOREIGN_KEY, $this->units->lineage($owner));
        $query->with(['resource', 'lessee', 'cooperation']);
        $query->orderBy(ResourceLease::VALID_FROM);

        return $query->get()->filter(static fn(ResourceLease $lease): bool => $lease->grants($access))->values();
    }

    /**
     * Obowiązujący dziś najem z publiczną tablicą o tokenie $token.
     */
    public function byWallboardToken(string $token): ?ResourceLease
    {
        if ($token === '') {
            return null;
        }

        $lease = $this->active()->where(ResourceLease::WALLBOARD_TOKEN, $token)->with(['resource', 'lessee', 'cooperation'])->first();

        return $lease !== null && $lease->grants(LeaseAccess::Wallboard) && $lease->inForceOn(CarbonImmutable::today()) ? $lease : null;
    }

    /**
     * @return Builder<ResourceLease>
     */
    private function active(): Builder
    {
        $query = ResourceLease::query();
        $query->where(ColumnNames::IS_ACTIVE, true);

        return $query;
    }

    /**
     * Aktywne najmy obowiązujące dziś albo później.
     *
     * @return Builder<ResourceLease>
     */
    private function current(): Builder
    {
        $query = $this->active();
        $query->where(static function (Builder $period): void {
            $period->whereNull(ResourceLease::VALID_TO)->orWhereDate(ResourceLease::VALID_TO, '>=', CarbonImmutable::today());
        });

        return $query;
    }
}
