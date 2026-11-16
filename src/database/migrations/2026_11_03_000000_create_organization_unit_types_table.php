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

/**
 * Słownik typów jednostek organizacyjnych (`organization_unit_types`: kod, prefiks numeru, ikona, struktura
 * wewnętrzna, domyślna widoczność w publicznym grafiku; typy domyślne dopisuje OrganizationUnitTypeSeeder)
 * oraz typ jednostki w organizacjach (`unit_type_id`, null — bez typu; usunięcie typu zostawia jednostkę bez typu).
 * Triggery historii organizacji są przebudowywane, a pula id organizacji rezerwowana ponownie (SQLite przebudowuje
 * tabelę przy kluczu obcym; w MySQL licznik zostaje nad istniejącymi id).
 */
return new class extends Migration {
    private const string TABLE = 'organization_unit_types';

    private const string ORGANIZATIONS = 'organizations';

    private const string UNIT_TYPE_ID = 'unit_type_id';

    public function up(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->string('code', 32)->unique();
            $table->string('number_prefix', 8)->default('');
            $table->string('icon', 32)->default('building');
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_public')->default(true);
            Columns::closing($table);
        });

        app(History::class)->create(self::TABLE);
        app(IdSlots::class)->reserve(self::TABLE);

        Schema::table(self::ORGANIZATIONS, static function (Blueprint $table): void {
            $table->foreignId(self::UNIT_TYPE_ID)->nullable()->after('is_department')->constrained(self::TABLE)->nullOnDelete();
        });

        app(History::class)->sync(self::ORGANIZATIONS);
        app(IdSlots::class)->reserve(self::ORGANIZATIONS);
    }

    public function down(): void
    {
        Schema::table(self::ORGANIZATIONS, static function (Blueprint $table): void {
            $table->dropConstrainedForeignId(self::UNIT_TYPE_ID);
        });

        app(History::class)->sync(self::ORGANIZATIONS);
        app(History::class)->drop(self::TABLE);
        Schema::dropIfExists(self::TABLE);
    }
};
