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

use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Domyślna organizacja kontekstu konta strefy, zapisana per osoba w `json_default_settings.organization` (id
 * organizacji): kontekst po zalogowaniu (SessionCurrentOrganization), o ile organizacja nadal jest kontekstem konta
 * (ActiveMemberships) — inaczej obowiązuje zwykły domyślny kontekst (hero: organizacja hero; reszta: najstarsze
 * członkostwo). W strefie z kontekstem roli (`role_contexts`) obok organizacji zapisana jest rola
 * (`json_default_settings.role`) — kontekst po zalogowaniu to wtedy para organizacja + rola. Ustawia ją konto (trasa
 * `account.organization.default`) albo plik instalacji (`accounts[].defaults`, bez roli).
 */
final readonly class DefaultOrganization
{
    /** Klucz ustawienia w `json_default_settings` konta. */
    public const string SETTING = 'organization';

    /** Klucz roli domyślnego kontekstu w `json_default_settings` konta. */
    public const string ROLE_SETTING = 'role';

    /**
     * Id zapisanej domyślnej organizacji konta albo null (brak ustawienia).
     */
    public function id(ZoneAccount $account): ?int
    {
        $id = $account->defaultSetting(self::SETTING);

        return is_int($id) ? $id : null;
    }

    /**
     * Rola zapisanego domyślnego kontekstu konta albo null (brak ustawienia).
     */
    public function role(ZoneAccount $account): ?string
    {
        $role = $account->defaultSetting(self::ROLE_SETTING);

        return is_string($role) && $role !== '' ? $role : null;
    }

    /**
     * Zapisuje organizację (i rolę; null usuwa zapisaną) jako domyślny kontekst konta; pozostałe ustawienia
     * `json_default_settings` zostają.
     *
     * @return bool Czy rekord się zmienił.
     */
    public function save(ZoneAccount $account, Organization $organization, ?string $role = null): bool
    {
        $id = $organization->getKey();

        if (! is_int($id) || ($this->id($account) === $id && $this->role($account) === $role)) {
            return false;
        }

        $account->putDefaultSettings([self::SETTING => $id, self::ROLE_SETTING => $role]);

        return true;
    }
}
