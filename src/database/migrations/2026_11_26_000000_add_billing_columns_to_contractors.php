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
use Simplysmart\Polygon\Models\Contractor;

/**
 * Dane do faktury kontrahenta (docs/baza-danych.md §19): pełna nazwa nabywcy, NIP, adres, kraj, osoba kontaktowa
 * i adres e-mail do wysyłki faktur — osobno od danych firmy, bo faktura może iść na inny podmiot albo adres.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table(Contractor::TABLE, static function (Blueprint $table): void {
            $table->string(Contractor::BILLING_NAME)->nullable()->after(Contractor::NOTES);
            $table->string(Contractor::BILLING_NIP, 10)->nullable()->after(Contractor::BILLING_NAME);
            $table->string(Contractor::BILLING_STREET)->nullable()->after(Contractor::BILLING_NIP);
            $table->string(Contractor::BILLING_POSTAL_CODE, 16)->nullable()->after(Contractor::BILLING_STREET);
            $table->string(Contractor::BILLING_CITY)->nullable()->after(Contractor::BILLING_POSTAL_CODE);
            $table->char(Contractor::BILLING_COUNTRY_CODE, 2)->nullable()->after(Contractor::BILLING_CITY);
            $table->string(Contractor::BILLING_CONTACT_NAME)->nullable()->after(Contractor::BILLING_COUNTRY_CODE);
            $table->string(Contractor::BILLING_EMAIL)->nullable()->after(Contractor::BILLING_CONTACT_NAME);
        });

        app(History::class)->sync(Contractor::TABLE);
    }

    public function down(): void
    {
        Schema::table(Contractor::TABLE, static function (Blueprint $table): void {
            $table->dropColumn(Contractor::BILLING_COLUMNS);
        });

        app(History::class)->sync(Contractor::TABLE);
    }
};
