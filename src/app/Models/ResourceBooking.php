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

use Simplysmart\Polygon\Localization\DateFormat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Simplysmart\Polygon\Calendar\Performer;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\BookingStatus;
use Simplysmart\Polygon\Models\Relations\BelongsToOrganization;

/**
 * Rezerwacja w kalendarzu (`resource_bookings`, docs/baza-danych.md §18): interakcja na zasobie (`resource_id`)
 * jednostki (`organization_id`) w przedziale `starts_at`–`ends_at`, z rodzajem nadanym przez paczkę strefy (`kind`,
 * np. `<strefa>.visit`), stanem (`status`, Enums\BookingStatus — stan dziedzinowy zamiast RecordStatus), wykonawcą
 * (`performer`) oraz kontem, które zapisało (`booked_by`) i potwierdziło (`confirmed_by`). Dane osoby i inne dane
 * dziedzinowe trzyma paczka strefy w swojej tabeli. `name` to termin, bez danych osobowych. Rezerwacja nadliczbowa
 * (isOverflow()) ma własną godzinę w kolejce po końcu bloku grafiku (ResourceSchedule::nextOverflowAt()) i nie zajmuje
 * slotów. Zapisywana tylko przez Calendar\BookSlot
 * i Calendar\BookingStatusChange.
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

    /**
     * Klucz `json_attributes` rezerwacji nadliczbowej (Calendar\BookSlot::bookOverflow()): dopisanej ponad sloty do kolejki
     * po końcu bloku grafiku — co `slot_minutes` od końca bloku, w pierwszym wolnym miejscu tej kolejki. Nie zajmuje
     * slotów (Calendar\FreeSlots ją pomija), więc nie blokuje następnego bloku.
     */
    public const string OVERFLOW = 'overflow';

    /**
     * Klucz `json_attributes` rezerwacji nadliczbowej: koniec bloku grafiku „HH:MM”, po którym stoi w kolejce — wiąże ją
     * z blokiem (ResourceSchedule::holds()), choć jej godzina jest już po nim.
     */
    public const string OVERFLOW_AFTER = 'overflow_after';

    /**
     * Klucz `json_attributes` rezerwacji od ręki (Calendar\BookSlot::bookInstant()): zapisanej na wskazaną godzinę
     * i zasób poza siatką slotów grafiku (np. wizyta lekarza zapisana w chwili rejestracji badania). Zajmuje swój czas
     * jak każda rezerwacja — wolne sloty (Calendar\FreeSlots) ją omijają.
     */
    public const string INSTANT = 'instant';

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
     * Czy to rezerwacja nadliczbowa — ponad sloty, na końcu bloku grafiku (OVERFLOW).
     */
    public function isOverflow(): bool
    {
        return $this->jsonAttribute(self::OVERFLOW) === true;
    }

    /**
     * Czy to rezerwacja od ręki — poza siatką slotów grafiku (INSTANT).
     */
    public function isInstant(): bool
    {
        return $this->jsonAttribute(self::INSTANT) === true;
    }

    /**
     * Koniec bloku grafiku „HH:MM”, po którym stoi rezerwacja nadliczbowa (OVERFLOW_AFTER); rezerwacja zapisana przed
     * kolejką (bez miejsca — hasOverflowPlace()) stała na samym końcu bloku — jej godzina. Zwykła rezerwacja — null.
     */
    public function overflowAfter(): ?string
    {
        if (! $this->isOverflow()) {
            return null;
        }

        $after = $this->jsonAttribute(self::OVERFLOW_AFTER);

        return is_string($after) ? $after : $this->starts_at->format(DateFormat::TIME);
    }

    /**
     * Czy rezerwacja nadliczbowa ma zapisane miejsce w kolejce bloku (OVERFLOW_AFTER) — bez niego to zapis sprzed kolejki
     * (Calendar\OverflowQueueRepair).
     */
    public function hasOverflowPlace(): bool
    {
        return $this->isOverflow() && is_string($this->jsonAttribute(self::OVERFLOW_AFTER));
    }

    /**
     * Ustawia rezerwację nadliczbową na miejscu kolejki bloku $schedule o $startsAt (ResourceSchedule::nextOverflowAt()):
     * termin na jeden slot, OVERFLOW i OVERFLOW_AFTER; pozostałe `json_attributes` zostają. Bez zapisu.
     */
    public function placeOverflow(ResourceSchedule $schedule, CarbonImmutable $startsAt): static
    {
        $attributes = $this->getAttribute(ColumnNames::JSON_ATTRIBUTES);

        return $this->forceFill([
            ColumnNames::NAME => $startsAt->format(DateFormat::DATE_TIME),
            self::STARTS_AT => $startsAt,
            self::ENDS_AT => $startsAt->addMinutes((int) $schedule->slot_minutes),
            ColumnNames::JSON_ATTRIBUTES => [...(is_array($attributes) ? $attributes : []), self::OVERFLOW => true, self::OVERFLOW_AFTER => $schedule->to()],
        ]);
    }

    public function isPerformedBy(ZoneAccount $account): bool
    {
        return $this->performer_type === $account->getMorphClass() && $this->performer_id === $account->id;
    }

    /**
     * Czy rezerwacja ma tego samego wykonawcę co blok grafiku.
     */
    public function sharesPerformer(ResourceSchedule $schedule): bool
    {
        return $this->performer_type === $schedule->performer_type && $this->performer_id === $schedule->performer_id;
    }

    /**
     * Kolejka rezerwacji: ta sama jednostka, zasób, wykonawca i dzień.
     */
    public function queueKey(): string
    {
        return implode('|', [$this->organization_id, $this->resource_id, (string) $this->performer_type, (string) $this->performer_id, $this->starts_at->format(DateFormat::DATE)]);
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
     * Konto strefy przyjmującego albo null (Calendar\Performer::account()).
     */
    public function performerAccount(): ?ZoneAccount
    {
        return Performer::account($this->performer);
    }

    /**
     * Nazwa przyjmującego do wyświetlenia; bez wykonawcy — pusty tekst (Calendar\Performer::name()).
     */
    public function performerName(): string
    {
        return Performer::name($this->performer);
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
