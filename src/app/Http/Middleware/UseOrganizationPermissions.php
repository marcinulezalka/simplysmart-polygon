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
use Illuminate\Http\Request;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Permissions\ContextPermissions;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zespół spatie żądania strefy: role i uprawnienia konta liczą się w jego bieżącej organizacji
 * (Contracts\CurrentOrganization). Hero i konto bez organizacji mają role globalne (zespół null). Stoi po StartSession
 * (sesja strefy z wyborem organizacji). Zespół przełącza i po odpowiedzi przywraca Permissions\ContextPermissions —
 * przez Permissions\OrganizationRoles, który też wczytuje od nowa role konta z guarda (to samo konto mogło mieć role
 * wczytane w innym zespole, np. w kolejnych żądaniach testu); w strefie z kontekstem roli (`role_contexts`) zawęża też
 * role konta do aktywnej (Contracts\CurrentOrganization::role(), Permissions\ActiveRole) do końca odpowiedzi.
 */
final readonly class UseOrganizationPermissions
{
    public function __construct(
        private ContextPermissions $context,
        private CurrentAccount $account,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $this->account->get();

        return $this->context->within($account instanceof ZoneAccount ? $account : null, static fn(): Response => $next($request));
    }
}
