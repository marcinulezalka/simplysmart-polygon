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
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Permissions\OrganizationRoles;
use Simplysmart\Polygon\Queries\Organizations\ActiveMemberships;

/**
 * Bieżąca organizacja zapamiętana w sesji strefy żądania (każda strefa ma osobną sesję, więc i osobny kontekst):
 * wybór konta, o ile nadal jest jego kontekstem (ActiveMemberships), inaczej domyślny — organizacja zapisana jako
 * domyślna konta (DefaultOrganization, `json_default_settings.organization`), gdy nadal jest jego kontekstem, albo
 * pierwszy kontekst konta (hero: organizacja hero; pozostali: najstarsze członkostwo) — zapisany w sesji, więc nowe
 * członkostwo nadane w trakcie sesji nie zmienia go samo.
 */
final readonly class SessionCurrentOrganization implements CurrentOrganization
{
    public const string SESSION_KEY = 'polygon.organization';

    /** Prefiks klucza sesji z rolą wybraną w organizacji (`polygon.role.<id organizacji>`). */
    public const string ROLE_SESSION_KEY = 'polygon.role';

    public function __construct(
        private CurrentAccount $account,
        private ActiveMemberships $memberships,
        private Request $request,
        private DefaultOrganization $default,
        private CurrentZone $zone,
        private OrganizationRoles $roles,
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

        $saved = $this->default->id($account);
        $default = $saved !== null && in_array($saved, $ids, true) ? $saved : $ids[0] ?? null;
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

    public function role(): ?string
    {
        $account = $this->roleAccount();
        $session = $this->session();
        $id = $this->id();

        if ($account === null || $session === null || $id === null) {
            return null;
        }

        $roles = $this->roles->names($account, $id);
        $chosen = $session->get(self::roleKey($id));

        if (is_string($chosen) && in_array($chosen, $roles, true)) {
            return $chosen;
        }

        $role = $this->defaultRole($account, $id, $roles);
        $role === null ? $session->forget(self::roleKey($id)) : $session->put(self::roleKey($id), $role);

        return $role;
    }

    public function contexts(): array
    {
        $account = $this->zoneAccount();

        if ($account === null) {
            return [];
        }

        $current = $this->id();
        $currentRole = $this->role();
        $default = $this->default->id($account);
        $roleAccount = $this->roleAccount();
        $contexts = [];

        foreach ($this->memberships->organizations($account) as $organization) {
            if ($organization->isHero()) {
                continue;
            }

            $roles = $roleAccount !== null ? $this->roles->ranked($roleAccount, $organization->id) : [];

            if ($roles === []) {
                $contexts[] = new OrganizationContext($organization, $organization->id === $current, $organization->id === $default);

                continue;
            }

            $defaultRole = $organization->id === $default ? $this->defaultRole($roleAccount, $organization->id, $roles) : null;

            foreach ($roles as $role) {
                $contexts[] = new OrganizationContext($organization, $organization->id === $current && $role === $currentRole, $role === $defaultRole, $role);
            }
        }

        return $contexts;
    }

    public function choose(Organization $organization, ?string $role = null): bool
    {
        $session = $this->session();
        $id = $organization->getKey();

        if ($session === null || ! is_int($id) || ! $this->offers($organization, $role)) {
            return false;
        }

        $session->put(self::SESSION_KEY, $id);

        if ($role !== null) {
            $session->put(self::roleKey($id), $role);
        }

        return true;
    }

    public function offers(Organization $organization, ?string $role): bool
    {
        $account = $this->zoneAccount();
        $id = $organization->getKey();

        if ($account === null || ! is_int($id) || ! $this->memberships->includes($account, $id)) {
            return false;
        }

        if ($role === null) {
            return true;
        }

        $roleAccount = $this->roleAccount();

        return $roleAccount !== null && in_array($role, $this->roles->names($roleAccount, $id), true);
    }

    /**
     * Konto żądania, gdy strefa ma kontekst roli i konto nie jest hero (hero pracuje na rolach globalnych).
     */
    private function roleAccount(): ?ZoneAccount
    {
        $account = $this->zoneAccount();

        if ($account === null || ! $this->zone->has() || ! $this->zone->get()->roleContexts) {
            return null;
        }

        return $this->memberships->isHero($account) ? null : $account;
    }

    /**
     * Domyślna rola konta w organizacji: rola zapisanego domyślnego kontekstu (gdy dotyczy tej organizacji i konto
     * nadal ją ma), inaczej najszersza.
     *
     * @param list<string> $roles Role konta w organizacji.
     */
    private function defaultRole(ZoneAccount $account, int $organizationId, array $roles): ?string
    {
        $saved = $this->default->id($account) === $organizationId ? $this->default->role($account) : null;

        return $saved !== null && in_array($saved, $roles, true) ? $saved : $this->roles->broadest($account, $organizationId);
    }

    private static function roleKey(int $organizationId): string
    {
        return self::ROLE_SESSION_KEY . '.' . $organizationId;
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
