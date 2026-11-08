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
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\BookingStatus;
use Simplysmart\Polygon\Models\Relations\BelongsToOrganization;

/**
 * Rezerwacja w kalendarzu (`resource_bookings`, docs/baza-danych.md §18): interakcja na zasobie (`resource_id`)
 * jednostki (`organization_id`) w przedziale `starts_at`–`ends_at`, z rodzajem nadanym przez paczkę strefy (`kind`,
 * np. `<strefa>.visit`), stanem (`status`, Enums\BookingStatus — stan dziedzinowy zamiast RecordStatus), wykonawcą
 * (`performer`) oraz kontem, które zapisało (`booked_by`) i potwierdziło (`confirmed_by`). Dane osoby i inne dane
 * dziedzinowe trzyma paczka strefy w swojej tabeli. `name` to termin, bez danych osobowych. Zapisywana tylko przez
 * Calendar\BookSlot i Calendar\BookingStatusChange.
 *
 * @property int $organization_id
 * @property int $resource_id
 * @property string|null $performer_type
 * @property int|null $performer_id
 * @property string $kind
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property BookingStatus $status
 * @property string|null $booked_by_type
 * @property int|null $booked_by_id
 * @property string|null $confirmed_by_type
 * @property int|null $confirmed_by_id
 * @property CarbonImmutable|null $confirmed_at
 * @property-read Organization|null $organization
 * @property-read OrganizationResource|null $resource
 * @property-read ZoneAccount|null $performer
 */
final class ResourceBooking extends DataModel
{
    use BelongsToOrganization;

    public const string TABLE = 'resource_bookings';

    /** Klucz obcy rezerwacji w tabelach paczek (np. wizyta lekarska). */
    public const string FOREIGN_KEY = 'booking_id';

    public const string KIND = 'kind';

    public const string STARTS_AT = 'starts_at';

    public const string ENDS_AT = 'ends_at';

    public const string BOOKED_BY = 'booked_by';

    public const string CONFIRMED_BY = 'confirmed_by';

    /** Czas potwierdzenia (Calendar\BookingStatusChange). */
    public const string CONFIRMED_AT = 'confirmed_at';

    protected $table = self::TABLE;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [ColumnNames::STATUS => 'booked', 'is_active' => true];

    public function usesRecordStatus(): bool
    {
        return false;
    }

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
        return $this->morphTo(ResourceSchedule::PERFORMER);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            Organization::FOREIGN_KEY => 'integer',
            OrganizationResource::FOREIGN_KEY => 'integer',
            ResourceSchedule::PERFORMER_TYPE => 'string',
            ResourceSchedule::PERFORMER_ID => 'integer',
            self::KIND => 'string',
            self::STARTS_AT => 'immutable_datetime',
            self::ENDS_AT => 'immutable_datetime',
            ColumnNames::STATUS => BookingStatus::class,
            'booked_by_type' => 'string',
            'booked_by_id' => 'integer',
            'confirmed_by_type' => 'string',
            self::CONFIRMED_AT => 'immutable_datetime',
            'confirmed_by_id' => 'integer',
        ];
    }
}
