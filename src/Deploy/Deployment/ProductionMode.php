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

namespace Simplysmart\Polygon\Deploy\Deployment;

/**
 * Co zrobić z produkcją po udanej aktualizacji przed-produkcji.
 */
enum ProductionMode
{
    /** Zapytać osobę wdrażającą (bez terminala — „nie”). */
    case Ask;

    /** Wdrożyć bez pytania (`--production`). */
    case Always;

    /** Pominąć (`--no-production`). */
    case Skip;

    public function describe(): string
    {
        return match ($this) {
            self::Ask => 'po potwierdzeniu pytania „Wdrożyć też na produkcję?” (bez terminala: nie).',
            self::Always => 'bez pytania (' . DeployOptions::PRODUCTION . ').',
            self::Skip => 'pominięta (' . DeployOptions::NO_PRODUCTION . ').',
        };
    }
}
