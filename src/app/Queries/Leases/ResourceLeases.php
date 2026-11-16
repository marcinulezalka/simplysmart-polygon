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
use Simplysmart\Polygon\Calendar\ValidityPeriod;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\LeaseAccess;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceBooking;
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
        ValidityPeriod::overlapping($query, $from, $to);

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
        $query->with(['resource', 'lessee', 'cooperation.parent']);
        $query->orderBy(ResourceLease::VALID_FROM);

        return $query->get()->filter(static fn(ResourceLease $lease): bool => $lease->grants($access))->values();
    }

    /**
     * Wizyty kooperacji na wynajętym zasobie w okresie $from–$to (włącznie), po stanie — do rozliczenia najmu.
     *
     * @return array<string, int> Wartość Enums\BookingStatus => liczba rezerwacji.
     */
    public function bookingCounts(ResourceLease $lease, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->bookingCountsFor([$lease], $from, $to)[$lease->id] ?? [];
    }

    /**
     * bookingCounts() wielu najmów jednym zapytaniem zgrupowanym po kooperacji, zasobie i stanie (lista najmów bez
     * zapytania na każdy najem).
     *
     * @param iterable<ResourceLease> $leases
     *
     * @return array<int, array<string, int>> Id najmu => (wartość Enums\BookingStatus => liczba rezerwacji).
     */
    public function bookingCountsFor(iterable $leases, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = [];
        $byPair = [];

        foreach ($leases as $lease) {
            $counts[$lease->id] = [];
            $byPair[$lease->cooperation_organization_id . ':' . $lease->resource_id][] = $lease->id;
        }

        if ($byPair === []) {
            return [];
        }

        $query = ResourceBooking::query();
        $query->where(static function (Builder $pairs) use ($byPair): void {
            foreach (array_keys($byPair) as $pair) {
                [$organizationId, $resourceId] = explode(':', $pair);
                $pairs->orWhere(static function (Builder $one) use ($organizationId, $resourceId): void {
                    $one->where(Organization::FOREIGN_KEY, (int) $organizationId)->where(OrganizationResource::FOREIGN_KEY, (int) $resourceId);
                });
            }
        });
        $query->where(ResourceBooking::STARTS_AT, '>=', $from->startOfDay());
        $query->where(ResourceBooking::STARTS_AT, '<', $to->startOfDay()->addDay());
        $query->select([Organization::FOREIGN_KEY, OrganizationResource::FOREIGN_KEY, ColumnNames::STATUS]);
        $query->selectRaw('count(*) as aggregate');
        $query->groupBy([Organization::FOREIGN_KEY, OrganizationResource::FOREIGN_KEY, ColumnNames::STATUS]);

        foreach ($query->toBase()->get() as $row) {
            $values = (array) $row;
            $status = $values[ColumnNames::STATUS] ?? null;
            $organizationId = $values[Organization::FOREIGN_KEY] ?? null;
            $resourceId = $values[OrganizationResource::FOREIGN_KEY] ?? null;

            if (! is_string($status) || ! is_numeric($organizationId) || ! is_numeric($resourceId) || ! is_numeric($values['aggregate'] ?? null)) {
                continue;
            }

            $pair = (int) $organizationId . ':' . (int) $resourceId;

            if (! isset($byPair[$pair])) {
                continue;
            }

            foreach ($byPair[$pair] as $leaseId) {
                $counts[$leaseId][$status] = (int) $values['aggregate'];
            }
        }

        return $counts;
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
        ValidityPeriod::overlapping($query, CarbonImmutable::today());

        return $query;
    }
}
