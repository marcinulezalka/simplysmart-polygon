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

/**
 * Członkostwo kont stref w organizacjach (docs/baza-danych.md §16): tabela łącząca morph, wspólna dla stref,
 * z historią i bez kolumn standardowych. Członkostwo jest niezależne od ról; usunięcie organizacji kasuje jej członków.
 */
return new class extends Migration {
    private const string TABLE = 'organization_members';

    public function up(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->morphs('model');
            $table->timestamps();
            $table->unique(['organization_id', 'model_id', 'model_type'], 'organization_members_member_unique');
        });

        app(History::class)->create(self::TABLE);
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            app(History::class)->drop(self::TABLE);
        }

        Schema::dropIfExists(self::TABLE);
    }
};
