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

use Simplysmart\Polygon\Contracts\HasLabel;
use Simplysmart\Polygon\Localization\PackageText;

/**
 * Katalog ról każdej strefy (sekcja `roles` pliku `database/install/polygon.json`, docs/instalacja.md): role
 * obowiązkowe (RequiredRole) i role dziedzinowe. Uprawnienia ustawia strefa; etykiety w `polygon::roles.<rola>`
 * (strefa może je nadpisać w `roles.<strefa>.<rola>`, Zones\ZoneDefinition::roleLabel()). Test projektu pilnuje
 * zgodności z plikiem.
 */
enum BaseRole: string implements HasLabel
{
    /** Konto bez uprawnień. */
    case Guest = 'guest';

    /** Wsparcie techniczne. */
    case Support = 'support';

    /** Właściciel strefy. */
    case Owner = 'owner';

    /** Hero (root): wszystkie uprawnienia strefy, najwyższa rola. */
    case Hero = 'hero';

    /** Rejestracja (obsługa przyjęć i terminów). */
    case Registration = 'registration';

    /** Lekarz. */
    case Doctor = 'doctor';

    /** Księgowość. */
    case Accountant = 'accountant';

    /** Analityk (raporty i zestawienia). */
    case Analyst = 'analyst';

    /** Manager (zarządzanie zespołem i pracą). */
    case Manager = 'manager';

    public function label(): string
    {
        return PackageText::get('roles.' . $this->value);
    }
}
