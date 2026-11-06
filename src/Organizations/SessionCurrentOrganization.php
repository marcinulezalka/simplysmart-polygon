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

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentOrganization;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Queries\Organizations\ActiveMemberships;

/**
 * Bieżąca organizacja zapamiętana w sesji strefy żądania (każda strefa ma osobną sesję, więc i osobny kontekst):
 * wybór konta, o ile nadal jest jego kontekstem (ActiveMemberships), inaczej domyślny — pierwszy kontekst konta
 * (hero: organizacja hero; pozostali: najstarsze członkostwo) — zapisany w sesji, więc nowe członkostwo nadane
 * w trakcie sesji nie zmienia go samo.
 */
final readonly class SessionCurrentOrganization implements CurrentOrganization
{
    public const string SESSION_KEY = 'polygon.organization';

    public function __construct(
        private CurrentAccount $account,
        private ActiveMemberships $memberships,
        private Request $request,
    ) {}

    public function get(): ?Organization
    {
        $id = $this->id();

        return $id === null ? null : Organization::query()->with('parent')->find($id);
    }

    public function id(): ?int
    {
        $account = $this->zoneAccount();
        $session = $this->session();

        if ($account === null || $session === null) {
            return null;
        }

        $ids = $this->memberships->ids($account);
        $chosen = $session->get(self::SESSION_KEY);

        if (is_int($chosen) && in_array($chosen, $ids, true)) {
            return $chosen;
        }

        $default = $ids[0] ?? null;
        $default === null ? $session->forget(self::SESSION_KEY) : $session->put(self::SESSION_KEY, $default);

        return $default;
    }

    public function visible(): ?Organization
    {
        $organization = $this->get();

        return $organization === null || $organization->isHero() ? null : $organization;
    }

    public function isHero(): bool
    {
        $account = $this->zoneAccount();

        return $account !== null && $this->memberships->isHero($account);
    }

    public function contexts(): array
    {
        $account = $this->zoneAccount();

        if ($account === null) {
            return [];
        }

        $current = $this->id();
        $contexts = [];

        foreach ($this->memberships->organizations($account) as $organization) {
            if (! $organization->isHero()) {
                $contexts[] = new OrganizationContext($organization, $organization->id === $current);
            }
        }

        return $contexts;
    }

    public function choose(Organization $organization): bool
    {
        $account = $this->zoneAccount();
        $session = $this->session();
        $id = $organization->getKey();

        if ($account === null || $session === null || ! is_int($id) || ! $this->memberships->includes($account, $id)) {
            return false;
        }

        $session->put(self::SESSION_KEY, $id);

        return true;
    }

    private function zoneAccount(): ?ZoneAccount
    {
        $account = $this->account->get();

        return $account instanceof ZoneAccount ? $account : null;
    }

    private function session(): ?Session
    {
        return $this->request->hasSession() ? $this->request->session() : null;
    }
}
