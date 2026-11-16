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
use Simplysmart\Polygon\Models\ResourceBooking;

/**
 * Czas potwierdzenia rezerwacji (docs/baza-danych.md): `confirmed_at` obok `confirmed_by`, ustawiany przez
 * Calendar\BookingStatusChange przy przejściu do stanu Confirmed.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table(ResourceBooking::TABLE, static function (Blueprint $table): void {
            $table->dateTime(ResourceBooking::CONFIRMED_AT)->nullable()->after('confirmed_by_id');
        });

        app(History::class)->sync(ResourceBooking::TABLE);
    }

    public function down(): void
    {
        Schema::table(ResourceBooking::TABLE, static function (Blueprint $table): void {
            $table->dropColumn(ResourceBooking::CONFIRMED_AT);
        });

        app(History::class)->sync(ResourceBooking::TABLE);
    }
};
