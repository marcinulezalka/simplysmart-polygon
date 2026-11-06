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
 * Wybór trzyma sesja strefy; bez wyboru (albo gdy wybrana organizacja przestała być dostępna) — domyślna: organizacja
 * hero dla hero, inaczej najstarsze członkostwo. Organizacja hero jest kontekstem, ale frontend stref jej nie pokazuje.
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
     * Organizacje do przełącznika frontendu, w kolejności kontekstów konta, bez organizacji hero.
     *
     * @return list<OrganizationContext>
     */
    public function contexts(): array;

    /**
     * Ustawia kontekst konta żądania; false, gdy organizacja nie jest jego kontekstem (nic się nie zmienia).
     */
    public function choose(Organization $organization): bool;
}
