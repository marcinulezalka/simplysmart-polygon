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
 * Cykl tygodni (docs/baza-danych.md §18, §20): `week_interval` grafiku i umowy najmu — co ile tygodni (1 — co tydzień),
 * licząc od tygodnia `valid_from` (Calendar\WeekCycle).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table(ResourceSchedule::TABLE, static function (Blueprint $table): void {
            $table->unsignedTinyInteger(ResourceSchedule::WEEK_INTERVAL)->default(1)->after(ResourceSchedule::SLOT_MINUTES);
        });
        Schema::table(ResourceLease::TABLE, static function (Blueprint $table): void {
            $table->unsignedTinyInteger(ResourceLease::WEEK_INTERVAL)->default(1)->after(ResourceLease::WINDOWS);
        });

        app(History::class)->sync(ResourceSchedule::TABLE);
        app(History::class)->sync(ResourceLease::TABLE);
    }

    public function down(): void
    {
        Schema::table(ResourceSchedule::TABLE, static function (Blueprint $table): void {
            $table->dropColumn(ResourceSchedule::WEEK_INTERVAL);
        });
        Schema::table(ResourceLease::TABLE, static function (Blueprint $table): void {
            $table->dropColumn(ResourceLease::WEEK_INTERVAL);
        });

        app(History::class)->sync(ResourceSchedule::TABLE);
        app(History::class)->sync(ResourceLease::TABLE);
    }
};
