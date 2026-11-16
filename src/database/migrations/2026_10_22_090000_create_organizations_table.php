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
 * Organizacje (firmy korzystające z aplikacji, drzewo przez `parent_id`), wspólne dla stref: flaga departamentu,
 * nazwa skrócona, kolor i dane firmy (wszystkie opcjonalne). Schemat w jednej migracji (bez osobnych migracji `add_*`);
 * typ jednostki (`unit_type_id`) dodaje migracja `create_organization_unit_types_table`. Lista kolumn danych firmy
 * jest zamrożona w COLUMNS (stan z tej migracji) — kolejne kolumny dochodzą własną migracją. NIP nie jest unikalny:
 * oddział może mieć NIP firmy.
 * Organizację hero „Simplysmart” (id 1) zakłada seeder (HeroOrganizationSeeder), nie migracja.
 */
return new class extends Migration {
    private const string TABLE = 'organizations';

    /**
     * @var array<string, int>
     */
    private const array COLUMNS = [
        'short_name' => 100,
        'color' => 7,
        'nip' => 10,
        'regon' => 14,
        'krs' => 10,
        'phone' => 32,
        'mobile_phone' => 32,
        'email' => 255,
        'billing_email' => 255,
        'street' => 255,
        'postal_code' => 10,
        'city' => 100,
        'country_code' => 2,
    ];

    public function up(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->boolean('is_department')->default(false);

            foreach (self::COLUMNS as $column => $length) {
                $table->string($column, $length)->nullable();
            }

            $table->index('nip');
            Columns::closing($table);
        });

        app(History::class)->create(self::TABLE);
        app(IdSlots::class)->reserve(self::TABLE);
    }

    public function down(): void
    {
        app(History::class)->drop(self::TABLE);
        Schema::dropIfExists(self::TABLE);
    }
};
