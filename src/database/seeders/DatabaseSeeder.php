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
use Simplysmart\Polygon\Database\PackageSeeders;

/**
 * Seeder główny projektu (`php artisan db:seed`, `migrate --seed`): PolygonServiceProvider rejestruje go pod nazwą
 * `DatabaseSeeder`, więc projekt nie potrzebuje własnego `database/seeders/`. Migracje nie wstawiają danych — wszystkie
 * dane referencyjne dają seedery: bazowe polygon (słowniki lokalizacji, typy jednostek i organizacja hero, pliki
 * instalacji: uprawnienia, role, konta startowe), potem seedery paczek (Database\PackageSeeders). Wszystkie są
 * referencyjne i idempotentne, także na produkcji; paczka nie tworzy danych demo.
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Nazwa, pod którą Laravel szuka seedera głównego, gdy w projekcie nie ma `Database\Seeders\DatabaseSeeder`.
     */
    public const string CONTAINER_NAME = 'DatabaseSeeder';

    /**
     * Seedery bazowe polygon w kolejności zależności (konta hero z plików instalacji dołączają do organizacji hero).
     *
     * @var list<class-string<Seeder>>
     */
    public const array BASE = [LocalizationSeeder::class, OrganizationsSeeder::class, InstallSeeder::class];

    public function __construct(
        private readonly PackageSeeders $packages,
    ) {}

    public function run(): void
    {
        $this->call([...self::BASE, ...$this->packages->all()]);
    }
}
