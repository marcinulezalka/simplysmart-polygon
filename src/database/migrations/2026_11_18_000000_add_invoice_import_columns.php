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
use Simplysmart\Polygon\Enums\InvoiceSource;
use Simplysmart\Polygon\Models\Invoice;
use Simplysmart\Polygon\Models\InvoicingAccount;

/**
 * Pobieranie faktur z systemu fakturowego (docs/baza-danych.md §21): faktura dostaje źródło (`source` — wystawiona
 * w aplikacji albo pobrana z Fakturowni), stan w Fakturowni (`external_status`), chwilę ostatniej zmiany w Fakturowni
 * (`external_updated_at`) i datę zapłaty (`paid_at`); konto — chwilę ostatniego pobrania faktur (`invoices_synced_at`),
 * przełącznik synchronizacji automatycznej (`auto_sync`) i raport ostatniej synchronizacji (`last_sync_report`).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table(Invoice::TABLE, static function (Blueprint $table): void {
            $table->string(Invoice::SOURCE, 16)->default(InvoiceSource::Local->value)->after('kind');
            $table->string(Invoice::EXTERNAL_STATUS, 32)->nullable()->after('external_url');
            $table->timestamp(Invoice::EXTERNAL_UPDATED_AT)->nullable()->after('external_status');
            $table->date(Invoice::PAID_AT)->nullable()->after('external_updated_at');
        });

        Schema::table(InvoicingAccount::TABLE, static function (Blueprint $table): void {
            $table->timestamp(InvoicingAccount::INVOICES_SYNCED_AT)->nullable()->after('contractors_synced_at');
            $table->boolean(InvoicingAccount::AUTO_SYNC)->default(true)->after('invoices_synced_at');
            $table->json(InvoicingAccount::LAST_SYNC_REPORT)->nullable()->after('auto_sync');
        });

        app(History::class)->sync(Invoice::TABLE);
        app(History::class)->sync(InvoicingAccount::TABLE);
    }

    public function down(): void
    {
        Schema::table(Invoice::TABLE, static function (Blueprint $table): void {
            $table->dropColumn([Invoice::SOURCE, Invoice::EXTERNAL_STATUS, Invoice::EXTERNAL_UPDATED_AT, Invoice::PAID_AT]);
        });

        Schema::table(InvoicingAccount::TABLE, static function (Blueprint $table): void {
            $table->dropColumn([InvoicingAccount::INVOICES_SYNCED_AT, InvoicingAccount::AUTO_SYNC, InvoicingAccount::LAST_SYNC_REPORT]);
        });

        app(History::class)->sync(Invoice::TABLE);
        app(History::class)->sync(InvoicingAccount::TABLE);
    }
};
