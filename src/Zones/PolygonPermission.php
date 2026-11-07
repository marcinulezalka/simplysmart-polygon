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
 * Uprawnienia strefy `polygon`: zestaw strefy administratorów oraz podgląd kont, zarządzanie rolami i słownikami; przypisania do ról są tylko w pliku instalacji
 * (`database/install/polygon.json`), etykiety w `polygon::permissions.polygon.*`.
 */
enum PolygonPermission: string implements HasLabel
{
    case ZonesView = 'zones.view';
    case ZonesConfigure = 'zones.configure';
    case AccountsView = 'accounts.view';
    case AccountsManage = 'accounts.manage';
    case RolesManage = 'roles.manage';
    case OrganizationsView = 'organizations.view';
    case OrganizationsManage = 'organizations.manage';
    case OwnersView = 'owners.view';
    case OwnersAssign = 'owners.assign';
    case SettingsView = 'settings.view';
    case SettingsManage = 'settings.manage';
    case DictionariesView = 'dictionaries.view';
    case DictionariesManage = 'dictionaries.manage';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';
    case HeroesManage = 'heroes.manage';
    case SystemMaintenance = 'system.maintenance';

    public function label(): string
    {
        return PackageText::get('permissions.' . PolygonZone::NAME . '.' . $this->value);
    }
}
