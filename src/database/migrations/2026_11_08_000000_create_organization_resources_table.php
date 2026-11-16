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

/**
 * Zasoby organizacji (`organization_resources`, docs/baza-danych.md §17): gabinety, sprzęt i inne zasoby należące do
 * jednej organizacji (`organization_id`), z rodzajem (`kind`, Enums\ResourceKind) i krótkim kodem (`code`, np. „G3”,
 * unikalnym w organizacji). Z zasobu korzysta organizacja właściciela i jej jednostki podrzędne
 * (Queries\Organizations\OrganizationResources). Usunięcia organizacji z zasobami baza nie dopuszcza.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create(OrganizationResource::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(Organization::FOREIGN_KEY)->constrained(Organization::TABLE)->restrictOnDelete();
            $table->string(OrganizationResource::KIND, 32)->index();
            $table->string(OrganizationResource::CODE, OrganizationResource::CODE_MAX_LENGTH)->nullable();
            Columns::closing($table);
            $table->unique([Organization::FOREIGN_KEY, OrganizationResource::CODE]);
        });

        app(History::class)->create(OrganizationResource::TABLE);
        app(IdSlots::class)->reserve(OrganizationResource::TABLE);
    }

    public function down(): void
    {
        if (Schema::hasTable(OrganizationResource::TABLE)) {
            app(History::class)->drop(OrganizationResource::TABLE);
        }

        Schema::dropIfExists(OrganizationResource::TABLE);
    }
};
