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
use Simplysmart\Polygon\Database\Seeders\MeasureUnitsSeeder;

/**
 * Jednostki miar krajów (słownik lokalizacji): symbol (`code`, np. `kg`, `szt.`), kod UN/ECE Recommendation 20
 * (`unece_code`, np. `KGM`; dokumenty elektroniczne) i rodzaj (Enums\MeasureUnitKind); unikalne kraj + symbol.
 * Jednostki podstawowe Polski, Wielkiej Brytanii i USA dopisuje MeasureUnitsSeeder (tylko krajom, które już są).
 */
return new class extends Migration {
    private const string TABLE = 'locale_measure_units';

    public function up(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId('country_id')->constrained('locale_countries')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('unece_code', 3)->nullable();
            $table->string('kind', 20);
            $table->unique(['country_id', 'code']);
            Columns::closing($table, i18n: true);
        });

        app(History::class)->create(self::TABLE);
        app(IdSlots::class)->reserve(self::TABLE);
        app(MeasureUnitsSeeder::class)->run();
    }

    public function down(): void
    {
        app(History::class)->drop(self::TABLE);
        Schema::dropIfExists(self::TABLE);
    }
};
