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
use Simplysmart\Polygon\Models\ContractorContract;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;
use Simplysmart\Polygon\Models\ResourceLease;

/**
 * Najem zasobów (docs/baza-danych.md §20): flaga `organizations.is_cooperation` (podorganizacja kooperacji najemcy)
 * i `resource_leases` — umowa najmu zasobu właściciela (`organization_id`) organizacji najemcy z jej podorganizacją
 * kooperacji, okresem, oknami godzinowymi w dniach tygodnia, poziomami udostępnienia i tokenem publicznej tablicy.
 * Usunięcia zasobu albo organizacji z najmem baza nie dopuszcza.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table(Organization::TABLE, static function (Blueprint $table): void {
            $table->boolean(Organization::IS_COOPERATION)->default(false)->after(Organization::IS_DEPARTMENT);
        });

        app(History::class)->sync(Organization::TABLE);

        Schema::create(ResourceLease::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(Organization::FOREIGN_KEY)->constrained(Organization::TABLE)->restrictOnDelete();
            $table->foreignId(OrganizationResource::FOREIGN_KEY)->constrained(OrganizationResource::TABLE)->restrictOnDelete();
            $table->foreignId(ResourceLease::LESSEE_ID)->constrained(Organization::TABLE)->restrictOnDelete();
            $table->foreignId(ResourceLease::COOPERATION_ID)->constrained(Organization::TABLE)->restrictOnDelete();
            $table->foreignId(ResourceLease::CONTRACT_ID)->nullable()->constrained(ContractorContract::TABLE)->nullOnDelete();
            $table->date(ResourceLease::VALID_FROM);
            $table->date(ResourceLease::VALID_TO)->nullable();
            $table->json(ResourceLease::WINDOWS)->nullable();
            $table->json(ResourceLease::ACCESS)->nullable();
            $table->string(ResourceLease::WALLBOARD_TOKEN, ResourceLease::WALLBOARD_TOKEN_LENGTH)->nullable()->unique();
            Columns::closing($table);
            $table->index([OrganizationResource::FOREIGN_KEY, ResourceLease::VALID_FROM]);
            $table->index([ResourceLease::COOPERATION_ID, OrganizationResource::FOREIGN_KEY]);
        });

        app(History::class)->create(ResourceLease::TABLE);
        app(IdSlots::class)->reserve(ResourceLease::TABLE);
    }

    public function down(): void
    {
        if (Schema::hasTable(ResourceLease::TABLE)) {
            app(History::class)->drop(ResourceLease::TABLE);
        }

        Schema::dropIfExists(ResourceLease::TABLE);

        Schema::table(Organization::TABLE, static function (Blueprint $table): void {
            $table->dropColumn(Organization::IS_COOPERATION);
        });

        app(History::class)->sync(Organization::TABLE);
    }
};
