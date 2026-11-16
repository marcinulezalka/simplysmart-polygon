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

namespace Simplysmart\Polygon\Http\Controllers\Account;

use Simplysmart\Polygon\Http\StatusMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use Simplysmart\Polygon\Contracts\CurrentOrganization;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Zones\ZoneRedirects;

/**
 * Przełączenie organizacji kontekstu konta (formularz POST z polem FIELD = token organizacji): tylko na organizację,
 * która jest kontekstem konta (Contracts\CurrentOrganization::choose()), potem pulpit strefy z komunikatem.
 */
final readonly class OrganizationSwitchController
{
    public const string FIELD = OrganizationField::NAME;

    public function __construct(
        private CurrentOrganization $organization,
        private CurrentZone $zone,
        private Redirector $redirect,
        private OrganizationField $field,
    ) {}

    /**
     * @throws ValidationException Gdy organizacji nie ma albo nie jest kontekstem konta.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $organization = $this->field->organization($request);

        if (! $this->organization->choose($organization)) {
            throw OrganizationField::unavailable();
        }

        return $this->redirect->route($this->zone->get()->routeName(ZoneRedirects::HOME_ROUTE))->with(StatusMessage::KEY, text('account.organization.switched', ['name' => $organization->contextName()]));
    }
}
