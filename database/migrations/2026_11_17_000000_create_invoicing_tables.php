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
use Simplysmart\Polygon\Models\Invoice;
use Simplysmart\Polygon\Models\InvoiceItem;
use Simplysmart\Polygon\Models\InvoicingAccount;
use Simplysmart\Polygon\Models\InvoicingContractorLink;
use Simplysmart\Polygon\Models\Organization;

/**
 * Faktury i integracja z systemem fakturowym (docs/baza-danych.md §21): `invoicing_accounts` — konto Fakturowni
 * organizacji głównej (subdomena, szyfrowany token API); `invoicing_contractor_links` — powiązanie kontrahenta
 * z klientem w Fakturowni (id zewnętrzne unikalne w koncie, jedno powiązanie kontrahenta w koncie); `invoices` —
 * faktury organizacji z migawką stron, sumami i identyfikatorami zewnętrznymi (id zewnętrzne unikalne w organizacji);
 * `invoice_items` — pozycje z migawką stawki. Organizacji i kontrahenta z fakturami baza nie pozwala usunąć; pozycje
 * i powiązania znikają z rekordem nadrzędnym.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create(InvoicingAccount::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(Organization::FOREIGN_KEY)->unique()->constrained(Organization::TABLE)->restrictOnDelete();
            $table->string(InvoicingAccount::PROVIDER, 32);
            $table->string(InvoicingAccount::SUBDOMAIN, 100);
            $table->text(InvoicingAccount::API_TOKEN);
            $table->string(InvoicingAccount::DEPARTMENT_ID, 32)->nullable();
            $table->timestamp(InvoicingAccount::CONTRACTORS_SYNCED_AT)->nullable();
            $table->text(InvoicingAccount::LAST_ERROR)->nullable();
            Columns::closing($table);
        });

        Schema::create(InvoicingContractorLink::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(InvoicingAccount::FOREIGN_KEY)->constrained(InvoicingAccount::TABLE)->cascadeOnDelete();
            $table->foreignId(Contractor::FOREIGN_KEY)->constrained(Contractor::TABLE)->cascadeOnDelete();
            $table->string(InvoicingContractorLink::EXTERNAL_ID, 32);
            $table->timestamp(InvoicingContractorLink::EXTERNAL_UPDATED_AT)->nullable();
            $table->timestamp(InvoicingContractorLink::SYNCED_AT)->nullable();
            $table->char(InvoicingContractorLink::SYNCED_CHECKSUM, 64)->nullable();
            Columns::closing($table);
            $table->unique([InvoicingAccount::FOREIGN_KEY, InvoicingContractorLink::EXTERNAL_ID], 'invoicing_links_external_unique');
            $table->unique([InvoicingAccount::FOREIGN_KEY, Contractor::FOREIGN_KEY], 'invoicing_links_contractor_unique');
        });

        Schema::create(Invoice::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(Organization::FOREIGN_KEY)->constrained(Organization::TABLE)->restrictOnDelete();
            $table->foreignId(Contractor::FOREIGN_KEY)->constrained(Contractor::TABLE)->restrictOnDelete();
            $table->string(Invoice::KIND, 32);
            $table->date(Invoice::ISSUE_DATE);
            $table->date(Invoice::SELL_DATE);
            $table->date(Invoice::PAYMENT_DUE)->nullable();
            $table->string(Invoice::PAYMENT_METHOD, 32);
            $table->char(Invoice::CURRENCY_CODE, 3);
            $table->decimal(Invoice::TOTAL_NET, 12, 2)->default(0);
            $table->decimal(Invoice::TOTAL_VAT, 12, 2)->default(0);
            $table->decimal(Invoice::TOTAL_GROSS, 12, 2)->default(0);

            foreach (['seller', 'buyer'] as $party) {
                $table->string($party . '_name')->nullable();
                $table->string($party . '_nip', 32)->nullable();
                $table->string($party . '_street')->nullable();
                $table->string($party . '_postal_code', 16)->nullable();
                $table->string($party . '_city')->nullable();
                $table->char($party . '_country_code', 2)->nullable();
            }

            $table->string(Invoice::EXTERNAL_ID, 32)->nullable();
            $table->string(Invoice::EXTERNAL_TOKEN, 64)->nullable();
            $table->string(Invoice::EXTERNAL_NUMBER, 64)->nullable();
            $table->string(Invoice::EXTERNAL_URL)->nullable();
            $table->timestamp(Invoice::SENT_AT)->nullable();
            $table->text(Invoice::ERROR)->nullable();
            $table->text(Invoice::NOTES)->nullable();
            Columns::closing($table);
            $table->unique([Organization::FOREIGN_KEY, Invoice::EXTERNAL_ID], 'invoices_external_unique');
            $table->index([Organization::FOREIGN_KEY, Invoice::ISSUE_DATE]);
            $table->index([Contractor::FOREIGN_KEY, Invoice::ISSUE_DATE]);
        });

        Schema::create(InvoiceItem::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(Invoice::FOREIGN_KEY)->constrained(Invoice::TABLE)->cascadeOnDelete();
            $table->decimal(InvoiceItem::QUANTITY, 12, 3)->default(1);
            $table->string(InvoiceItem::UNIT, 16)->nullable();
            $table->decimal(InvoiceItem::UNIT_PRICE_NET, 12, 2)->default(0);
            $table->foreignId(InvoiceItem::TAX_RATE_ID)->nullable()->constrained('locale_tax_rates')->nullOnDelete();
            $table->string(InvoiceItem::TAX, 32);
            $table->decimal(InvoiceItem::TAX_PERCENT, 5, 2)->nullable();
            $table->decimal(InvoiceItem::NET, 12, 2)->default(0);
            $table->decimal(InvoiceItem::VAT, 12, 2)->default(0);
            $table->decimal(InvoiceItem::GROSS, 12, 2)->default(0);
            Columns::closing($table);
        });

        foreach ([InvoicingAccount::TABLE, InvoicingContractorLink::TABLE, Invoice::TABLE, InvoiceItem::TABLE] as $name) {
            app(History::class)->create($name);
            app(IdSlots::class)->reserve($name);
        }
    }

    public function down(): void
    {
        foreach ([InvoiceItem::TABLE, Invoice::TABLE, InvoicingContractorLink::TABLE, InvoicingAccount::TABLE] as $name) {
            if (Schema::hasTable($name)) {
                app(History::class)->drop($name);
            }

            Schema::dropIfExists($name);
        }
    }
};
