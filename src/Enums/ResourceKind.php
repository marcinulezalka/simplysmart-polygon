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

namespace Simplysmart\Polygon\Enums;

use Simplysmart\Polygon\Localization\PackageText;

/**
 * Rodzaj zasobu organizacji (kolumna `kind` tabeli `organization_resources`): gabinet albo pomieszczenie przyjęć,
 * sprzęt i inny zasób. Teksty: `enums.resource_kind.<value>`.
 */
enum ResourceKind: string
{
    case Room = 'room';
    case Equipment = 'equipment';
    case Other = 'other';

    public function label(): string
    {
        return PackageText::get('enums.resource_kind.' . $this->value);
    }
}
