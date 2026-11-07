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

namespace Simplysmart\Polygon\Queries\Organizations;

use Illuminate\Database\Eloquent\Collection;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;

/**
 * Organizacja i jej aktywne jednostki podrzędne na każdym poziomie, bez organizacji hero (of()), oraz jej przodkowie
 * (lineage()): zakres pracy konta w strefie
 * (np. w kontekście siedziby — jej poradnie, w kontekście poradni — tylko ona). Ona pierwsza, potem pozostałe po nazwie.
 */
final readonly class OrganizationUnits
{
    /** Najgłębsze drzewo, które jest przeszukiwane (ochrona przed cyklem w danych). */
    private const int MAX_DEPTH = 32;

    /**
     * Id organizacji i jej przodków, od niej w górę (zasoby i kontrahenci przodków są dostępni w jednostce).
     *
     * @return list<int>
     */
    public function lineage(Organization $organization): array
    {
        $ids = [];
        $current = $organization->id;

        while (is_int($current) && ! in_array($current, $ids, true) && count($ids) < self::MAX_DEPTH) {
            $ids[] = $current;
            $parent = Organization::query()->whereKey($current)->value(ColumnNames::PARENT_ID);
            $current = is_numeric($parent) ? (int) $parent : null;
        }

        return $ids;
    }

    /**
     * @return Collection<int, Organization>
     */
    public function of(Organization $organization): Collection
    {
        $ids = [];
        $level = [$organization->id];

        for ($depth = 0; $level !== [] && $depth < self::MAX_DEPTH; $depth++) {
            $level = array_values(array_diff($level, $ids));
            $ids = [...$ids, ...$level];
            $query = Organization::query();
            $query->whereIn(ColumnNames::PARENT_ID, $level);
            $query->where(ColumnNames::IS_ACTIVE, true);
            $query->whereKeyNot(Organization::HERO_ID);
            $level = array_map(static fn(Organization $unit): int => $unit->id, $query->get([ColumnNames::ID])->all());
        }

        $query = Organization::query();
        $query->whereKey($ids);
        $query->whereKeyNot(Organization::HERO_ID);
        $query->with('parent');

        return $query->get()->sortBy(static fn(Organization $unit): string => ($unit->is($organization) ? '0' : '1') . mb_strtolower($unit->contextName()))->values();
    }
}
