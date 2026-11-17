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

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentOrganization;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Http\StatusMessage;
use Simplysmart\Polygon\Organizations\DefaultOrganization;
use Simplysmart\Polygon\Organizations\OrganizationContext;

/**
 * Zapis domyślnego kontekstu konta (formularz POST z polem OrganizationField::NAME = token organizacji i opcjonalnym
 * OrganizationField::ROLE — rola w strefie z `role_contexts`): tylko para, która jest kontekstem konta
 * (Contracts\CurrentOrganization::offers()); trafia do `json_default_settings` konta (DefaultOrganization)
 * i obowiązuje od następnego logowania — bieżącego kontekstu nie zmienia. Wraca na poprzednią stronę z komunikatem.
 */
final readonly class OrganizationDefaultController
{
    public function __construct(
        private CurrentAccount $account,
        private CurrentOrganization $organization,
        private CurrentZone $zone,
        private DefaultOrganization $default,
        private Redirector $redirect,
        private OrganizationField $field,
    ) {}

    /**
     * @throws ValidationException Gdy organizacji nie ma albo para organizacja + rola nie jest kontekstem konta.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $organization = $this->field->organization($request);
        $role = $this->field->role($request);
        $account = $this->account->require();

        if (! $this->organization->offers($organization, $role)) {
            throw OrganizationField::unavailable();
        }

        $this->default->save($account, $organization, $role);
        $name = (new OrganizationContext($organization, false, role: $role))->label($this->zone->get());

        return $this->redirect->back()->with(StatusMessage::KEY, text('account.organization.default_saved', ['name' => $name]));
    }
}
