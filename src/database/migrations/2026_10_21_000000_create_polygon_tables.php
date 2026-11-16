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
use Simplysmart\Polygon\Database\ZoneTables;

/**
 * Tabele strefy polygon (paczka simplysmart/polygon): konta, kody logowania, sesje i zaufane urządzenia.
 */
return new class extends Migration {
    private const string ZONE = 'polygon';

    public function up(): void
    {
        app(ZoneTables::class)->create(self::ZONE);
    }

    public function down(): void
    {
        app(ZoneTables::class)->drop(self::ZONE);
    }
};
