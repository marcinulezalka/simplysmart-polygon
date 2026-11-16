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

namespace Simplysmart\Polygon\Database;

use Illuminate\Database\Seeder;

/**
 * Seedery paczek (stref i innych paczek aplikacji) uruchamiane przez seeder główny po seederach bazowych polygon
 * (Database\Seeders\DatabaseSeeder), w kolejności rejestracji providerów. Paczka dodaje swój seeder w register():
 * `$this->callAfterResolving(PackageSeeders::class, static fn(PackageSeeders $seeders) => $seeders->add(ShopSeeder::class))`.
 * Singleton (Providers\DatabaseServiceProvider).
 */
final class PackageSeeders
{
    /**
     * @var list<class-string<Seeder>>
     */
    private array $seeders = [];

    /**
     * @param class-string<Seeder> $seeder Seeder referencyjny paczki (idempotentny, bez danych demo).
     */
    public function add(string $seeder): void
    {
        if (! in_array($seeder, $this->seeders, true)) {
            $this->seeders[] = $seeder;
        }
    }

    /**
     * @return list<class-string<Seeder>>
     */
    public function all(): array
    {
        return $this->seeders;
    }
}
