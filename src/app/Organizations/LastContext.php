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

use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Ostatni kontekst wybrany przez konto (organizacja i rola), zapisany w `json_default_settings.last_context`: po
 * wygaśnięciu sesji nowa sesja zaczyna od niego, a nie od kontekstu domyślnego (SessionCurrentOrganization), więc konto
 * wraca na stronę otwartą przed logowaniem w tej samej roli. Wylogowanie (Auth\SignIn\SignOut) go usuwa — następne
 * logowanie zaczyna od kontekstu domyślnego.
 */
final readonly class LastContext
{
    /** Klucz ustawienia w `json_default_settings` konta. */
    public const string SETTING = 'last_context';

    private const string ORGANIZATION = 'organization';

    private const string ROLE = 'role';

    public function organizationId(ZoneAccount $account): ?int
    {
        $id = $this->saved($account)[self::ORGANIZATION] ?? null;

        return is_int($id) ? $id : null;
    }

    /**
     * Rola ostatniego kontekstu, gdy dotyczy on organizacji $organizationId.
     */
    public function role(ZoneAccount $account, int $organizationId): ?string
    {
        $role = $this->saved($account)[self::ROLE] ?? null;

        return $this->organizationId($account) === $organizationId && is_string($role) ? $role : null;
    }

    public function save(ZoneAccount $account, int $organizationId, ?string $role): void
    {
        $context = [self::ORGANIZATION => $organizationId, self::ROLE => $role];

        if ($this->saved($account) != $context) {
            $account->putDefaultSettings([self::SETTING => $context]);
        }
    }

    public function forget(ZoneAccount $account): void
    {
        if ($this->saved($account) !== []) {
            $account->putDefaultSettings([self::SETTING => null]);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function saved(ZoneAccount $account): array
    {
        $saved = $account->defaultSetting(self::SETTING);

        return is_array($saved) ? $saved : [];
    }
}
