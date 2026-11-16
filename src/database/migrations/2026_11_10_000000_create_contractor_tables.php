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
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\ContractorContact;
use Simplysmart\Polygon\Models\ContractorContract;
use Simplysmart\Polygon\Models\Organization;

/**
 * Kontrahenci organizacji (docs/baza-danych.md §19): `contractors` — firmy, z którymi organizacja pracuje (np. kierujące
 * pracowników na badania), z danymi firmy i NIP-em unikalnym w organizacji; `contractor_contacts` — osoby kontaktowe;
 * `contractor_contracts` — umowy z okresem obowiązywania. Kontakty i umowy znikają z kontrahentem; organizacji
 * z kontrahentami baza nie pozwala usunąć.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create(Contractor::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(Organization::FOREIGN_KEY)->constrained(Organization::TABLE)->restrictOnDelete();
            $table->string(Contractor::SHORT_NAME, 64)->nullable();
            $table->string(Contractor::NIP, 10)->nullable();
            $table->string(Contractor::REGON, 14)->nullable();
            $table->string(Contractor::KRS, 10)->nullable();
            $table->string(Contractor::STREET)->nullable();
            $table->string(Contractor::POSTAL_CODE, 16)->nullable();
            $table->string(Contractor::CITY)->nullable();
            $table->char(Contractor::COUNTRY_CODE, 2)->nullable();
            $table->string(Contractor::EMAIL)->nullable();
            $table->string(Contractor::PHONE, 32)->nullable();
            $table->text(Contractor::NOTES)->nullable();
            Columns::closing($table);
            $table->unique([Organization::FOREIGN_KEY, Contractor::NIP]);
            $table->index([Organization::FOREIGN_KEY, 'name']);
        });

        Schema::create(ContractorContact::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(Contractor::FOREIGN_KEY)->constrained(Contractor::TABLE)->cascadeOnDelete();
            $table->string(ContractorContact::ROLE)->nullable();
            $table->string(ContractorContact::PHONE, 32)->nullable();
            $table->string(ContractorContact::EMAIL)->nullable();
            $table->boolean(ContractorContact::IS_PRIMARY)->default(false);
            Columns::closing($table);
        });

        Schema::create(ContractorContract::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(Contractor::FOREIGN_KEY)->constrained(Contractor::TABLE)->cascadeOnDelete();
            $table->date(ContractorContract::SIGNED_ON)->nullable();
            $table->date(ContractorContract::VALID_FROM);
            $table->date(ContractorContract::VALID_TO)->nullable();
            $table->text(ContractorContract::NOTES)->nullable();
            Columns::closing($table);
            $table->index([Contractor::FOREIGN_KEY, ContractorContract::VALID_TO]);
        });

        foreach ([Contractor::TABLE, ContractorContact::TABLE, ContractorContract::TABLE] as $name) {
            app(History::class)->create($name);
            app(IdSlots::class)->reserve($name);
        }
    }

    public function down(): void
    {
        foreach ([ContractorContract::TABLE, ContractorContact::TABLE, Contractor::TABLE] as $name) {
            if (Schema::hasTable($name)) {
                app(History::class)->drop($name);
            }

            Schema::dropIfExists($name);
        }
    }
};
