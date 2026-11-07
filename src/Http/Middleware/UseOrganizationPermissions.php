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

namespace Simplysmart\Polygon\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentOrganization;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zespół spatie żądania strefy: role i uprawnienia konta liczą się w jego bieżącej organizacji
 * (Contracts\CurrentOrganization). Hero i konto bez organizacji mają role globalne (zespół null). Stoi po StartSession
 * (sesja strefy z wyborem organizacji); po odpowiedzi przywraca poprzedni zespół.
 */
final readonly class UseOrganizationPermissions
{
    public function __construct(
        private PermissionRegistrar $registrar,
        private CurrentOrganization $organization,
        private CurrentAccount $account,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previous = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($this->organization->isHero() ? null : $this->organization->id());
        $this->forgetLoadedRoles();

        try {
            return $next($request);
        } finally {
            $this->registrar->setPermissionsTeamId($previous);
        }
    }

    /**
     * Konto z guarda może mieć role wczytane w innym zespole (to samo konto w kolejnych żądaniach testu).
     */
    private function forgetLoadedRoles(): void
    {
        $account = $this->account->get();

        if ($account instanceof Model) {
            $account->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
