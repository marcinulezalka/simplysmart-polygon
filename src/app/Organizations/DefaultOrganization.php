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

namespace Simplysmart\Polygon\Organizations;

use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Domyślna organizacja kontekstu konta strefy, zapisana per osoba w `json_default_settings.organization` (id
 * organizacji): kontekst po zalogowaniu (SessionCurrentOrganization), o ile organizacja nadal jest kontekstem konta
 * (ActiveMemberships) — inaczej obowiązuje zwykły domyślny kontekst (hero: organizacja hero; reszta: najstarsze
 * członkostwo). Ustawia ją konto (trasa `account.organization.default`) albo plik instalacji (`accounts[].defaults`).
 */
final readonly class DefaultOrganization
{
    /** Klucz ustawienia w `json_default_settings` konta. */
    public const string SETTING = 'organization';

    /**
     * Id zapisanej domyślnej organizacji konta albo null (brak ustawienia).
     */
    public function id(ZoneAccount $account): ?int
    {
        $id = $account->defaultSetting(self::SETTING);

        return is_int($id) ? $id : null;
    }

    /**
     * Zapisuje organizację jako domyślną konta; pozostałe ustawienia `json_default_settings` zostają.
     *
     * @return bool Czy rekord się zmienił.
     */
    public function save(ZoneAccount $account, Organization $organization): bool
    {
        $id = $organization->getKey();

        if (! is_int($id) || $this->id($account) === $id) {
            return false;
        }

        $current = $account->getAttribute(ColumnNames::JSON_DEFAULT_SETTINGS);
        $account->forceFill([ColumnNames::JSON_DEFAULT_SETTINGS => [...(is_array($current) ? $current : []), self::SETTING => $id]])->save();

        return true;
    }
}
