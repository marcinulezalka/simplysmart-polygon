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

namespace Simplysmart\Polygon\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Simplysmart\Polygon\Calendar\ValidityPeriod;
use Simplysmart\Polygon\Calendar\WeekCycle;
use Simplysmart\Polygon\Models\Relations\BelongsToOrganization;

/**
 * Grafik zasobu (`resource_schedules`, docs/baza-danych.md §18): dostępność zasobu (`resource_id`) dla jednostki
 * (`organization_id`) w dniu tygodnia ISO (`weekday`, 1 = poniedziałek), w przedziale `starts_at`–`ends_at`
 * (`HH:MM:SS`), opcjonalnie z wykonawcą (`performer` — konto strefy, np. lekarz) i podziałem na sloty po
 * `slot_minutes` minut, co `week_interval` tygodni od tygodnia `valid_from` (1 — co tydzień, Calendar\WeekCycle), ważna od
 * `valid_from` do `valid_to` (bez końca: null). Z `fills_gaps` grafik nie zajmuje zasobu na wyłączność — wypełnia jego wolny
 * czas (najem na żądanie): terminy tylko poza innym grafikiem zasobu, oknami najmu na wyłączność i rezerwacjami. `name` to przedział „HH:MM–HH:MM”.
 * Zapisywany tylko przez Calendar\SaveSchedule (bez kolizji zasobu, wykonawcy i najmu), instalator i wypowiedzenie
 * najmu (Leases\SaveLease::terminate() skraca okres grafiku najemcy).
 *
 * @property int $organization_id
 * @property int $resource_id
 * @property string|null $performer_type
 * @property int|null $performer_id
 * @property int $weekday
 * @property string $starts_at
 * @property string $ends_at
 * @property int|null $slot_minutes
 * @property int $week_interval
 * @property bool $fills_gaps
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_to
 * @property-read Organization|null $organization
 * @property-read OrganizationResource|null $resource
 * @property-read ZoneAccount|null $performer
 */
final class ResourceSchedule extends DataModel
{
    use BelongsToOrganization;

    public const string TABLE = 'resource_schedules';

    /** Nazwa relacji morph wykonawcy (kolumny `performer_type`, `performer_id`). */
    public const string PERFORMER = 'performer';

    public const string PERFORMER_TYPE = 'performer_type';

    public const string PERFORMER_ID = 'performer_id';

    public const string WEEKDAY = 'weekday';

    public const string STARTS_AT = 'starts_at';

    public const string ENDS_AT = 'ends_at';

    public const string SLOT_MINUTES = 'slot_minutes';

    public const string WEEK_INTERVAL = 'week_interval';

    /** Grafik wypełniający wolny czas zasobu (najem na żądanie): bez wyłączności zasobu. */
    public const string FILLS_GAPS = 'fills_gaps';

    public const string VALID_FROM = ValidityPeriod::FROM;

    public const string VALID_TO = ValidityPeriod::TO;

    protected $table = self::TABLE;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true, self::WEEK_INTERVAL => 1, self::FILLS_GAPS => false];

    /**
     * @return BelongsTo<OrganizationResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(OrganizationResource::class, OrganizationResource::FOREIGN_KEY);
    }

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function performer(): MorphTo
    {
        return $this->morphTo(self::PERFORMER);
    }

    /**
     * Początek przedziału „HH:MM”.
     */
    public function from(): string
    {
        return substr($this->starts_at, 0, 5);
    }

    /**
     * Koniec przedziału „HH:MM”.
     */
    public function to(): string
    {
        return substr($this->ends_at, 0, 5);
    }

    /**
     * Cykl tygodni grafiku (od tygodnia `valid_from`).
     */
    public function cycle(): WeekCycle
    {
        return new WeekCycle($this->week_interval, $this->valid_from);
    }

    /**
     * Czy grafik obowiązuje w dniu $day (dzień tygodnia, cykl tygodni i okres ważności).
     */
    public function appliesOn(CarbonImmutable $day): bool
    {
        $day = $day->startOfDay();

        return $day->dayOfWeekIso === $this->weekday
            && $day->greaterThanOrEqualTo($this->valid_from->startOfDay())
            && ($this->valid_to === null || $day->lessThanOrEqualTo($this->valid_to->startOfDay()))
            && $this->cycle()->occursOn($day);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            Organization::FOREIGN_KEY => 'integer',
            OrganizationResource::FOREIGN_KEY => 'integer',
            self::PERFORMER_TYPE => 'string',
            self::PERFORMER_ID => 'integer',
            self::WEEKDAY => 'integer',
            self::STARTS_AT => 'string',
            self::ENDS_AT => 'string',
            self::SLOT_MINUTES => 'integer',
            self::WEEK_INTERVAL => 'integer',
            self::FILLS_GAPS => 'boolean',
            self::VALID_FROM => 'immutable_date',
            self::VALID_TO => 'immutable_date',
        ];
    }
}
