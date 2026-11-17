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
use Simplysmart\Polygon\EventLog\EventLog;

/**
 * Dziennik zdarzeń konta (docs/baza-danych.md → „Dziennik zdarzeń konta”): tabela techniczna `account_events`
 * (powiadomienia, bezpieczeństwo, API — bez historii i standardowych kolumn, tylko dopisywana) i indeks aktora
 * (`actor_zone`, `actor_id`) w każdej istniejącej tabeli historii — nowe dostają go z History::create().
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create(EventLog::TABLE, static function (Blueprint $table): void {
            $table->id();
            $table->string(EventLog::ZONE, 64);
            $table->unsignedBigInteger(EventLog::ACCOUNT_ID);
            $table->string(EventLog::CHANNEL, 32);
            $table->string(EventLog::TYPE, 64);
            $table->string(EventLog::MESSAGE, EventLog::MESSAGE_LENGTH)->nullable();
            $table->json(EventLog::CONTEXT)->nullable();
            $table->string(EventLog::IP, 45)->nullable();
            $table->timestamp(EventLog::CREATED_AT)->nullable();
            $table->index([EventLog::ZONE, EventLog::ACCOUNT_ID, EventLog::CREATED_AT]);
        });

        foreach ($this->historyTables() as $table) {
            $source = substr($table, 0, -strlen(History::TABLE_SUFFIX));

            if (! Schema::hasIndex($table, History::actorIndex($source))) {
                Schema::table($table, static fn(Blueprint $history) => $history->index(['actor_zone', 'actor_id'], History::actorIndex($source)));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(EventLog::TABLE);
    }

    /**
     * @return list<string>
     */
    private function historyTables(): array
    {
        return array_values(array_filter(Schema::getTableListing(schemaQualified: false), static fn(mixed $table): bool => is_string($table) && str_ends_with($table, History::TABLE_SUFFIX)));
    }
};
