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

namespace Simplysmart\Polygon\Permissions;

/**
 * Role, które musi mieć każda strefa w pliku instalacji (jak w admiro), z `hero` jako rolą root.
 */
enum RequiredRole: string
{
    /** Konto bez uprawnień: widzi tylko trasy publiczne i dla zalogowanych. */
    case Guest = 'guest';

    /** Wsparcie techniczne; domyślnie bez danych wrażliwych. */
    case Support = 'support';

    /** Właściciel strefy lub organizacji. */
    case Owner = 'owner';

    /** Root strefy: zawsze wszystkie uprawnienia (`"*"` bez `except`), najwyższa rola. */
    case Hero = 'hero';

    public const self ROOT = self::Hero;
}
