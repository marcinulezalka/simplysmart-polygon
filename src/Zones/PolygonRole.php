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

namespace Simplysmart\Polygon\Zones;

use Simplysmart\Polygon\Contracts\HasLabel;
use Simplysmart\Polygon\Localization\PackageText;

/**
 * Role strefy `polygon` jak w strefie administratorów admiro (definicje w pliku instalacji
 * `database/install/polygon.json`, etykiety w `polygon::roles.polygon.*`). Test projektu pilnuje zgodności z plikiem.
 */
enum PolygonRole: string implements HasLabel
{
    /** Konto bez uprawnień. */
    case Guest = 'guest';

    /** Wsparcie techniczne: podgląd. */
    case Support = 'support';

    /** Właściciel systemu: wszystko poza zarządzaniem superadministratorami i konserwacją. */
    case Owner = 'owner';

    /** Hero (root): wszystkie uprawnienia, najwyższa rola. */
    case Hero = 'hero';

    public function label(): string
    {
        return PackageText::get('roles.' . PolygonZone::NAME . '.' . $this->value);
    }
}
