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

namespace Simplysmart\Polygon\Contracts;

use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Organizations\OrganizationContext;

/**
 * Organizacja, w której kontekście pracuje konto zalogowane w strefie żądania (docs/strefy.md, „Kontekst organizacji”).
 * Wybór trzyma sesja strefy; bez wyboru (albo gdy wybrana organizacja przestała być dostępna) — domyślna: zapisana
 * domyślna organizacja konta (Organizations\DefaultOrganization), o ile nadal jest kontekstem, inaczej organizacja hero
 * dla hero, a dla reszty najstarsze członkostwo. Organizacja hero jest kontekstem, ale frontend stref jej nie pokazuje.
 * W strefie z kontekstem roli (`role_contexts`) kontekst to para organizacja + jedna rola konta w niej (role()):
 * uprawnienia liczą się tylko z tej roli (Permissions\ActiveRole), a przełącznik ma pozycję na każdą parę.
 */
interface CurrentOrganization
{
    /**
     * Bieżąca organizacja albo null (gość, konto bez organizacji, żądanie poza strefą).
     */
    public function get(): ?Organization;

    public function id(): ?int;

    /**
     * Bieżąca organizacja do pokazania we frontendzie: bez organizacji hero (wtedy null).
     */
    public function visible(): ?Organization;

    /**
     * Czy konto żądania jest hero (członkiem organizacji hero).
     */
    public function isHero(): bool;

    /**
     * Aktywna rola konta w bieżącej organizacji (strefa z `role_contexts`): wybrana w sesji, o ile konto nadal ją ma,
     * inaczej rola zapisanego domyślnego kontekstu tej organizacji, inaczej najszersza
     * (Permissions\OrganizationRoles::broadest()). Null: strefa bez kontekstu roli, hero, brak organizacji albo ról.
     */
    public function role(): ?string;

    /**
     * Konteksty do przełącznika frontendu, w kolejności organizacji konta, bez organizacji hero; w strefie
     * z `role_contexts` pozycja na każdą rolę konta w organizacji (od najważniejszej —
     * Permissions\OrganizationRoles::ranked(); organizacja bez ról — jedna pozycja bez roli).
     *
     * @return list<OrganizationContext>
     */
    public function contexts(): array;

    /**
     * Ustawia kontekst konta żądania (organizację i — w strefie z `role_contexts` — rolę; null: zapamiętana albo
     * domyślna rola tej organizacji); false, gdy para nie jest jego kontekstem (nic się nie zmienia).
     */
    public function choose(Organization $organization, ?string $role = null): bool;

    /**
     * Czy organizacja i rola (null: bez roli) są kontekstem konta żądania — do walidacji formularzy kontekstu.
     */
    public function offers(Organization $organization, ?string $role): bool;
}
