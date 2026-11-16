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
use Simplysmart\Polygon\Database\IdSlots;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Permissions\PermissionTables as T;

/**
 * Zespoły spatie/laravel-permission: zespół to organizacja (docs/baza-danych.md §15). `roles`, `model_has_roles`
 * i `model_has_permissions` dostają `organization_id` (null — przypisanie globalne: hero, konta bez organizacji;
 * istniejące przypisania zostają globalne), przypisanie jest unikalne w organizacji, a triggery historii obejmują nową
 * kolumnę. Nowy indeks unikalny powstaje przed usunięciem starego, bo MySQL trzyma na nim klucz obcy roli/uprawnienia.
 * SQLite przebudowuje tabelę przy kluczu obcym (gubi licznik id), więc pula id ról jest rezerwowana ponownie — bez
 * skutków dla istniejących wierszy (oba silniki biorą większą z wartości: licznik albo najwyższe id).
 */
return new class extends Migration {
    /**
     * Tabela łącząca => [kolumna roli/uprawnienia, stary indeks unikalny, nowy indeks unikalny].
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const array ASSIGNMENTS = [
        T::MODEL_HAS_PERMISSIONS => [T::PERMISSION_FOREIGN_KEY, 'model_has_permissions_assignment_unique', 'model_has_permissions_team_assignment_unique'],
        T::MODEL_HAS_ROLES => [T::ROLE_FOREIGN_KEY, 'model_has_roles_assignment_unique', 'model_has_roles_team_assignment_unique'],
    ];

    public function up(): void
    {
        Schema::table(T::ROLES, static function (Blueprint $table): void {
            $table->foreignId(T::TEAM_FOREIGN_KEY)->nullable()->constrained(Organization::TABLE)->cascadeOnDelete();
        });
        app(IdSlots::class)->reserve(T::ROLES);

        foreach (self::ASSIGNMENTS as $name => [$key, $old, $new]) {
            Schema::table($name, static function (Blueprint $table) use ($key, $new): void {
                $table->foreignId(T::TEAM_FOREIGN_KEY)->nullable()->constrained(Organization::TABLE)->cascadeOnDelete();
                $table->unique([$key, T::MODEL_ID, T::MODEL_TYPE, T::TEAM_FOREIGN_KEY], $new);
            });

            Schema::table($name, static function (Blueprint $table) use ($old): void {
                $table->dropUnique($old);
            });
        }

        foreach (T::TEAM_TABLES as $name) {
            app(History::class)->sync($name);
        }
    }

    public function down(): void
    {
        foreach (self::ASSIGNMENTS as $name => [$key, $old, $new]) {
            Schema::table($name, static function (Blueprint $table) use ($key, $old): void {
                $table->unique([$key, T::MODEL_ID, T::MODEL_TYPE], $old);
            });

            Schema::table($name, static function (Blueprint $table) use ($new): void {
                $table->dropUnique($new);
                $table->dropConstrainedForeignId(T::TEAM_FOREIGN_KEY);
            });
        }

        Schema::table(T::ROLES, static function (Blueprint $table): void {
            $table->dropConstrainedForeignId(T::TEAM_FOREIGN_KEY);
        });
        app(IdSlots::class)->reserve(T::ROLES);

        foreach (T::TEAM_TABLES as $name) {
            app(History::class)->sync($name);
        }
    }
};
