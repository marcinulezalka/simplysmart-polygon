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
use Simplysmart\Polygon\Models\NoreplyMailboxAccount;
use Simplysmart\Polygon\Models\Organization;

/**
 * Nadawca SMTP „noreply” organizacji (`noreply_mailbox_accounts`, docs/baza-danych.md §22): jeden na organizację
 * główną klienta (Organization::FOREIGN_KEY), nadpisujący ustawienia SMTP z `.env` dla poczty wysyłanej w imieniu tej
 * organizacji, gdy skonfigurowany — host, port, szyfrowanie, login, hasło (szyfrowane, cast `encrypted`; nigdy nie
 * trafia do widoków ani logów), adres nadawcy (`from_address`) i ostatni błąd połączenia. Puste `host` — brak
 * nadpisania (Mail\NoreplyMailboxAccounts::configured(), Mail\OrganizationMailer::mailerFor()). Zapis —
 * Mail\SaveNoreplyMailboxAccount, wysyła Mail\NoreplyMailer.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create(NoreplyMailboxAccount::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(Organization::FOREIGN_KEY)->unique()->constrained(Organization::TABLE)->cascadeOnDelete();
            $table->string(NoreplyMailboxAccount::HOST, 255)->nullable();
            $table->unsignedSmallInteger(NoreplyMailboxAccount::PORT)->nullable();
            $table->string(NoreplyMailboxAccount::ENCRYPTION, 10)->nullable();
            $table->string(NoreplyMailboxAccount::USERNAME, 255)->nullable();
            $table->text(NoreplyMailboxAccount::PASSWORD)->nullable();
            $table->string(NoreplyMailboxAccount::FROM_ADDRESS, 255)->nullable();
            $table->text(NoreplyMailboxAccount::LAST_ERROR)->nullable();
            Columns::closing($table);
        });

        app(History::class)->create(NoreplyMailboxAccount::TABLE);
    }

    public function down(): void
    {
        app(History::class)->drop(NoreplyMailboxAccount::TABLE);

        Schema::dropIfExists(NoreplyMailboxAccount::TABLE);
    }
};
