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

namespace Simplysmart\Polygon\Queries\Contractors;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Identifiers\Nip;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Queries\Organizations\OrganizationUnits;

/**
 * Kontrahenci dostępni w organizacji: jej i jej przodków (kontrahent siedziby jest kontrahentem poradni), po nazwie.
 * Wyszukiwanie: same cyfry (co najmniej 3) — początek NIP-u, inaczej fragment nazwy albo nazwy skróconej. Wynik
 * domyślnie bez ucinania (pickery); $limit i $offset dociągają strony przy przewijaniu ekranu listy.
 */
final readonly class ContractorSearch
{
    /** Najmniej cyfr, od których fraza jest początkiem NIP-u. */
    private const int NIP_PREFIX_MIN = 3;

    public function __construct(
        private OrganizationUnits $units,
    ) {}

    /**
     * @return Collection<int, Contractor>
     */
    public function in(Organization $organization, ?string $term = null, bool $withInactive = false, bool $descending = false, ?int $limit = null, int $offset = 0): Collection
    {
        $query = $this->query($organization);

        if (! $withInactive) {
            $query->where(ColumnNames::IS_ACTIVE, true);
        }

        $term = trim((string) $term);
        $digits = Nip::normalise($term);

        if ($term !== '' && $digits !== '' && strlen($digits) >= self::NIP_PREFIX_MIN && preg_match('/^[\d\s\-]+$/', preg_replace('/^\s*PL/i', '', $term) ?? '') === 1) {
            $query->where(Contractor::NIP, 'like', $digits . '%');
        } elseif ($term !== '') {
            $query->where(static function (Builder $name) use ($term): void {
                $name->where(ColumnNames::NAME, 'like', '%' . $term . '%')->orWhere(Contractor::SHORT_NAME, 'like', '%' . $term . '%');
            });
        }

        $query->orderBy(ColumnNames::NAME, $descending ? 'desc' : 'asc');
        $query->orderBy(ColumnNames::ID, $descending ? 'desc' : 'asc');

        if ($limit !== null) {
            $query->skip($offset)->limit($limit);
        }

        return $query->get();
    }

    public function findByToken(Organization $organization, string $token): ?Contractor
    {
        return $this->query($organization)->where(ColumnNames::TOKEN, $token)->first();
    }

    public function includes(Organization $organization, int $contractorId): bool
    {
        return $this->query($organization)->whereKey($contractorId)->exists();
    }

    /**
     * Pierwszy kontrahent o $nip wśród $organizationIds (najpierw organizacji wyżej w drzewie — niższe id organizacji).
     *
     * @param list<int> $organizationIds
     */
    public function firstByNip(array $organizationIds, string $nip): ?Contractor
    {
        $query = Contractor::query();
        $query->whereIn(Organization::FOREIGN_KEY, $organizationIds);
        $query->where(Contractor::NIP, $nip);
        $query->orderBy(Organization::FOREIGN_KEY);

        return $query->first();
    }

    /**
     * @return Builder<Contractor>
     */
    private function query(Organization $organization): Builder
    {
        $query = Contractor::query();
        $query->whereIn(Organization::FOREIGN_KEY, $this->units->lineage($organization));

        return $query;
    }
}
