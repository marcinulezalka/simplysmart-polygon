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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simplysmart\Polygon\Database\Columns;
use Simplysmart\Polygon\Database\History;
use Simplysmart\Polygon\Database\IdSlots;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceBooking;
use Simplysmart\Polygon\Models\ResourceSchedule;

/**
 * Kalendarz zasobów (docs/baza-danych.md §18): `resource_schedules` — dostępność zasobu w jednostce w dniu tygodnia
 * (przedział, opcjonalnie wykonawca — konto dowolnej strefy — i długość slotu, okres ważności); `resource_bookings` —
 * interakcje na zasobie w przedziale czasu (rezerwacja z rodzajem nadawanym przez paczkę strefy, np. wizyta
 * lekarska), ze stanem, wykonawcą oraz kontem, które zapisało i potwierdziło. Usunięcia jednostki albo zasobu
 * z grafikiem lub rezerwacjami baza nie dopuszcza.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create(ResourceSchedule::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(Organization::FOREIGN_KEY)->constrained(Organization::TABLE)->restrictOnDelete();
            $table->foreignId(OrganizationResource::FOREIGN_KEY)->constrained(OrganizationResource::TABLE)->restrictOnDelete();
            $table->nullableMorphs(ResourceSchedule::PERFORMER);
            $table->unsignedTinyInteger(ResourceSchedule::WEEKDAY);
            $table->time(ResourceSchedule::STARTS_AT);
            $table->time(ResourceSchedule::ENDS_AT);
            $table->unsignedSmallInteger(ResourceSchedule::SLOT_MINUTES)->nullable();
            $table->date(ResourceSchedule::VALID_FROM);
            $table->date(ResourceSchedule::VALID_TO)->nullable();
            Columns::closing($table);
            $table->index([OrganizationResource::FOREIGN_KEY, ResourceSchedule::WEEKDAY]);
        });

        Schema::create(ResourceBooking::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(Organization::FOREIGN_KEY)->constrained(Organization::TABLE)->restrictOnDelete();
            $table->foreignId(OrganizationResource::FOREIGN_KEY)->constrained(OrganizationResource::TABLE)->restrictOnDelete();
            $table->nullableMorphs(ResourceSchedule::PERFORMER);
            $table->string(ResourceBooking::KIND, 64)->index();
            $table->dateTime(ResourceBooking::STARTS_AT);
            $table->dateTime(ResourceBooking::ENDS_AT);
            $table->nullableMorphs(ResourceBooking::BOOKED_BY);
            $table->nullableMorphs(ResourceBooking::CONFIRMED_BY);
            Columns::closing($table);
            $table->index([OrganizationResource::FOREIGN_KEY, ResourceBooking::STARTS_AT]);
            $table->index([Organization::FOREIGN_KEY, ResourceBooking::STARTS_AT]);
        });

        foreach ([ResourceSchedule::TABLE, ResourceBooking::TABLE] as $name) {
            app(History::class)->create($name);
            app(IdSlots::class)->reserve($name);
        }
    }

    public function down(): void
    {
        foreach ([ResourceBooking::TABLE, ResourceSchedule::TABLE] as $name) {
            if (Schema::hasTable($name)) {
                app(History::class)->drop($name);
            }

            Schema::dropIfExists($name);
        }
    }
};
