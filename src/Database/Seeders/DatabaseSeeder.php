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

namespace Simplysmart\Polygon\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder główny projektu (`php artisan db:seed`, `migrate --seed`): PolygonServiceProvider rejestruje go pod nazwą
 * `DatabaseSeeder`, więc projekt nie potrzebuje własnego `database/seeders/`. Wczytuje słowniki lokalizacji, potem
 * domyślną konfigurację systemu z plików instalacji (InstallSeeder: uprawnienia, role, konta startowe). Oba seedery są
 * referencyjne i idempotentne, także na produkcji; paczka nie tworzy danych demo.
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Nazwa, pod którą Laravel szuka seedera głównego, gdy w projekcie nie ma `Database\Seeders\DatabaseSeeder`.
     */
    public const string CONTAINER_NAME = 'DatabaseSeeder';

    public function run(): void
    {
        $this->call([LocalizationSeeder::class, InstallSeeder::class]);
    }
}
