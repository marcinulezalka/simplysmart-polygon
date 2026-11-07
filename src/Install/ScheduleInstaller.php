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
use Simplysmart\Polygon\Calendar\SaveSchedule;
use Simplysmart\Polygon\Calendar\ScheduleInput;
use Simplysmart\Polygon\Calendar\ScheduleRejected;
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceSchedule;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Queries\Accounts\ZoneAccounts;
use Simplysmart\Polygon\Queries\Organizations\OrganizationResources;

/**
 * Zakłada grafik organizacji z plików instalacji (`organizations.<klucz>.schedule`) po strefach (konta już są):
 * zasób po kluczu z pliku wśród zasobów organizacji i jej przodków oraz zasobów wynajętych jej jako kooperacji, wykonawca po strefie i adresie. Wpis o tym samym
 * zasobie, wykonawcy, dniu i godzinach zostaje bez zmian; nowy przechodzi przez Calendar\SaveSchedule (kolizje). Brak
 * zasobu, strefy, konta albo kolizja to InvalidInstallFile — transakcja instalatora jest wycofywana.
 */
final readonly class ScheduleInstaller
{
    public function __construct(
        private SaveSchedule $save,
        private ZoneRegistry $zones,
        private ZoneAccounts $accounts,
        private OrganizationResources $resources,
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
            foreach ($organization->schedules as $entry) {
                $this->entry($organization->key, $installed[$organization->key], $entry, $report);
            }
        }
    }

    /**
     * @throws InvalidInstallFile
     */
    private function entry(string $key, Organization $unit, InstallSchedule $entry, InstallReport $report): void
    {
        $where = "organization [{$key}] schedule";
        $resource = $this->resource($unit, $entry->resource) ?? throw InvalidInstallFile::merged("{$where} refers to the unknown resource [{$entry->resource}].");
        $performer = $this->performer($entry) ?? throw InvalidInstallFile::merged("{$where} refers to the unknown account [{$entry->performerEmail}] of zone [{$entry->performerZone}].");
        $description = "{$performer->displayName()} · {$unit->name} · " . self::weekday($entry->weekday) . " {$entry->from}–{$entry->to} · {$resource->label()}";

        if ($this->exists($unit, $resource, $performer, $entry)) {
            $report->schedulesKept[] = $description;

            return;
        }

        try {
            $this->save->save(new ScheduleInput(
                $unit->id,
                $resource->id,
                $performer,
                $entry->weekday,
                $entry->from,
                $entry->to,
                $entry->slot,
                $entry->validFrom === null ? CarbonImmutable::today() : CarbonImmutable::parse($entry->validFrom),
                $entry->validTo === null ? null : CarbonImmutable::parse($entry->validTo),
            ));
        } catch (ScheduleRejected $rejected) {
            throw InvalidInstallFile::merged("{$where} [{$description}] is rejected: " . $rejected->getMessage());
        }

        $report->schedulesCreated[] = $description;
    }

    private function resource(Organization $unit, string $key): ?OrganizationResource
    {
        foreach ($this->resources->available($unit) as $resource) {
            $attributes = $resource->getAttribute(ColumnNames::JSON_ATTRIBUTES);

            if (is_array($attributes) && ($attributes[OrganizationInstaller::INSTALL_KEY] ?? null) === $key) {
                return $resource;
            }
        }

        return null;
    }

    private function performer(InstallSchedule $entry): ?ZoneAccount
    {
        if (! $this->zones->has($entry->performerZone) || $this->zones->get($entry->performerZone)->model === null) {
            return null;
        }

        return $this->accounts->findByEmail($this->zones->get($entry->performerZone), $entry->performerEmail);
    }

    private function exists(Organization $unit, OrganizationResource $resource, ZoneAccount $performer, InstallSchedule $entry): bool
    {
        $query = ResourceSchedule::query();
        $query->where(Organization::FOREIGN_KEY, $unit->id);
        $query->where(OrganizationResource::FOREIGN_KEY, $resource->id);
        $query->where(ResourceSchedule::PERFORMER_TYPE, $performer->getMorphClass());
        $query->where(ResourceSchedule::PERFORMER_ID, $performer->id);
        $query->where(ResourceSchedule::WEEKDAY, $entry->weekday);
        $query->where(ResourceSchedule::STARTS_AT, ScheduleInput::time($entry->from));
        $query->where(ResourceSchedule::ENDS_AT, ScheduleInput::time($entry->to));

        return $query->exists();
    }

    /**
     * Nazwa dnia tygodnia ISO w języku Carbona (Laravel ustawia go na język aplikacji).
     */
    private static function weekday(int $weekday): string
    {
        return CarbonImmutable::parse('2024-01-01')->addDays($weekday - 1)->isoFormat('dddd');
    }
}
