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

use Closure;
use Simplysmart\Polygon\Contracts\CurrentOrganization;
use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Uprawnienia konta w jego bieżącym kontekście (Contracts\CurrentOrganization): działanie biegnie w zespole spatie
 * bieżącej organizacji (hero i konto bez organizacji — role globalne), a w strefie z kontekstem roli
 * (`role_contexts`) z rolami konta zawężonymi do aktywnej (Permissions\ActiveRole). Korzystają z tego middleware
 * żądania strefy (Http\Middleware\UseOrganizationPermissions) i strona startowa po zalogowaniu (Zones\ZoneRedirects),
 * liczona jeszcze w żądaniu logowania.
 */
final readonly class ContextPermissions
{
    public function __construct(
        private OrganizationRoles $roles,
        private CurrentOrganization $organization,
        private ActiveRole $activeRole,
    ) {}

    /**
     * Uruchamia działanie w kontekście konta i przywraca poprzedni zespół oraz zdejmuje zawężenie roli.
     *
     * @template TResult
     *
     * @param Closure(): TResult $action
     *
     * @return TResult
     */
    public function within(?ZoneAccount $account, Closure $action): mixed
    {
        $organizationId = $this->organization->isHero() ? null : $this->organization->id();

        return $this->roles->within($account, $organizationId, function () use ($account, $organizationId, $action): mixed {
            $role = $account !== null && $organizationId !== null ? $this->organization->role() : null;

            if ($account === null || $organizationId === null || $role === null) {
                return $action();
            }

            $this->activeRole->set($account, $organizationId, $role);
            $account->unsetRelation('roles')->unsetRelation('permissions');

            try {
                return $action();
            } finally {
                $this->activeRole->clear();
            }
        });
    }
}
