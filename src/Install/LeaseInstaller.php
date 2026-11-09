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

namespace Simplysmart\Polygon\Install;

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Leases\LeaseInput;
use Simplysmart\Polygon\Leases\LeaseRejected;
use Simplysmart\Polygon\Leases\LeaseWindows;
use Simplysmart\Polygon\Leases\SaveLease;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceLease;

/**
 * Zakłada umowy najmu z plików instalacji (`organizations.<właściciel>.leases`) po organizacjach i zasobach, przed
 * grafikiem (grafik najemcy wymaga umowy): zasób po kluczu z pliku wśród zasobów właściciela. Umowa o tym samym
 * zasobie, kooperacji i numerze zostaje bez zmian; nowa przechodzi przez Leases\SaveLease (kolizje). Brak zasobu albo
 * kolizja to InvalidInstallFile — transakcja instalatora jest wycofywana.
 */
final readonly class LeaseInstaller
{
    public function __construct(
        private SaveLease $save,
    ) {}

    /**
     * @param list<InstallOrganization> $organizations
     * @param array<string, Organization> $installed Klucz => organizacja (OrganizationInstaller).
     *
     * @throws InvalidInstallFile
     */
    public function install(array $organizations, array $installed, InstallReport $report): void
    {
        foreach ($organizations as $organization) {
            foreach ($organization->leases as $entry) {
                $this->entry($organization->key, $installed[$organization->key], $entry, $installed, $report);
            }
        }
    }

    /**
     * @param array<string, Organization> $installed
     *
     * @throws InvalidInstallFile
     */
    private function entry(string $key, Organization $owner, InstallLease $entry, array $installed, InstallReport $report): void
    {
        $where = "organization [{$key}] lease [{$entry->number}]";
        $resource = $this->resource($owner, $entry->resource) ?? throw InvalidInstallFile::merged("{$where} refers to the unknown resource [{$entry->resource}].");
        $lessee = $installed[$entry->lessee];
        $cooperation = $installed[$entry->cooperation];
        $description = "{$entry->number} · {$resource->label()} · {$owner->name} → {$lessee->name} ({$cooperation->name})";

        if ($this->exists($resource, $cooperation, $entry->number)) {
            $report->leasesKept[] = $description;

            return;
        }

        try {
            $this->save->save(new LeaseInput(
                $owner,
                $resource->id,
                $lessee,
                $cooperation,
                $entry->number,
                $entry->validFrom === null ? CarbonImmutable::today() : CarbonImmutable::parse($entry->validFrom),
                $entry->validTo === null ? null : CarbonImmutable::parse($entry->validTo),
                new LeaseWindows($entry->windows),
                $entry->access,
                null,
                $entry->weekInterval,
                $entry->mode,
                $entry->signedOn === null ? null : CarbonImmutable::parse($entry->signedOn),
            ));
        } catch (LeaseRejected $rejected) {
            throw InvalidInstallFile::merged("{$where} [{$description}] is rejected: " . $rejected->getMessage());
        }

        $report->leasesCreated[] = $description;
    }

    private function resource(Organization $owner, string $key): ?OrganizationResource
    {
        $query = OrganizationResource::query();
        $query->where(Organization::FOREIGN_KEY, $owner->id);
        $query->where(ColumnNames::JSON_ATTRIBUTES . '->' . OrganizationInstaller::INSTALL_KEY, $key);

        return $query->first();
    }

    private function exists(OrganizationResource $resource, Organization $cooperation, string $number): bool
    {
        $query = ResourceLease::query();
        $query->where(OrganizationResource::FOREIGN_KEY, $resource->id);
        $query->where(ResourceLease::COOPERATION_ID, $cooperation->id);
        $query->where(ColumnNames::NAME, $number);

        return $query->exists();
    }
}
