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
use Simplysmart\Polygon\Database\History;
use Simplysmart\Polygon\Models\ResourceLease;
use Simplysmart\Polygon\Models\ResourceSchedule;

/**
 * Najem na żądanie (docs/baza-danych.md §18, §20): `resource_leases.mode` (Enums\LeaseMode, domyślnie okna na
 * wyłączność) i `resource_schedules.fills_gaps` — grafik wypełniający wolny czas zasobu (bez wyłączności).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table(ResourceLease::TABLE, static function (Blueprint $table): void {
            $table->string(ResourceLease::MODE, 16)->default('windows')->after(ResourceLease::WEEK_INTERVAL);
        });
        Schema::table(ResourceSchedule::TABLE, static function (Blueprint $table): void {
            $table->boolean(ResourceSchedule::FILLS_GAPS)->default(false)->after(ResourceSchedule::WEEK_INTERVAL);
        });

        app(History::class)->sync(ResourceLease::TABLE);
        app(History::class)->sync(ResourceSchedule::TABLE);
    }

    public function down(): void
    {
        Schema::table(ResourceLease::TABLE, static function (Blueprint $table): void {
            $table->dropColumn(ResourceLease::MODE);
        });
        Schema::table(ResourceSchedule::TABLE, static function (Blueprint $table): void {
            $table->dropColumn(ResourceSchedule::FILLS_GAPS);
        });

        app(History::class)->sync(ResourceLease::TABLE);
        app(History::class)->sync(ResourceSchedule::TABLE);
    }
};
