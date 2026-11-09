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

/**
 * Data zawarcia umowy najmu (docs/baza-danych.md §20): `signed_on` obok okresu umowy — tylko informacja o podpisaniu,
 * bez wpływu na grafik; okres i cykl tygodni okien liczą się od `valid_from` (rozpoczęcie cyklu).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table(ResourceLease::TABLE, static function (Blueprint $table): void {
            $table->date(ResourceLease::SIGNED_ON)->nullable()->after(ResourceLease::CONTRACT_ID);
        });

        app(History::class)->sync(ResourceLease::TABLE);
    }

    public function down(): void
    {
        Schema::table(ResourceLease::TABLE, static function (Blueprint $table): void {
            $table->dropColumn(ResourceLease::SIGNED_ON);
        });

        app(History::class)->sync(ResourceLease::TABLE);
    }
};
