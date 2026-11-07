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

use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;

/**
 * Zakłada zasoby organizacji z plików instalacji, rozpoznawane po kluczu w organizacji (`json_attributes.install_key`,
 * także w koszu): brakujący powstaje aktywny, istniejący zostaje bez zmian (zmiany z aplikacji nie są cofane). Działa
 * w transakcji instalatora (Installer), po założeniu organizacji.
 */
final readonly class ResourceInstaller
{
    /**
     * @param list<InstallResource> $resources
     */
    public function install(Organization $organization, array $resources, InstallReport $report): void
    {
        foreach ($resources as $resource) {
            $existing = OrganizationResource::withTrashed()
                ->where(Organization::FOREIGN_KEY, $organization->getKey())
                ->where(ColumnNames::JSON_ATTRIBUTES . '->' . OrganizationInstaller::INSTALL_KEY, $resource->key)
                ->first();

            if ($existing !== null) {
                $report->resourcesKept[] = $existing->name;

                continue;
            }

            (new OrganizationResource())->forceFill([
                Organization::FOREIGN_KEY => $organization->getKey(),
                ColumnNames::NAME => $resource->name,
                ColumnNames::IS_ACTIVE => true,
                OrganizationResource::KIND => $resource->kind,
                OrganizationResource::CODE => $resource->code,
                ColumnNames::JSON_ATTRIBUTES => [OrganizationInstaller::INSTALL_KEY => $resource->key],
            ])->save();

            $report->resourcesCreated[] = $resource->name;
        }
    }
}
