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

namespace Simplysmart\Polygon\Install;

/**
 * Wynik instalacji: adresy kont założonych teraz i już istniejących (tylko z dopisanymi rolami).
 */
final class InstallReport
{
    /**
     * @var list<string>
     */
    public array $created = [];

    /**
     * @var list<string>
     */
    public array $kept = [];
}
