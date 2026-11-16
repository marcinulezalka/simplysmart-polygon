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

namespace Simplysmart\Polygon\Install;

use Simplysmart\Polygon\Permissions\RequiredRole;

/**
 * Konto startowe strefy z pliku instalacji: adres, nazwa, role, organizacje (personel) z rolami w każdej z nich, czy
 * dostaje losowe id z puli seederów (1–999) i atrybuty konta (`json_attributes`).
 *
 * Role `roles` są globalne, gdy konto nie ma organizacji albo jest hero (hero pracuje na rolach globalnych); w przeciwnym
 * razie konto dostaje je w każdej swojej organizacji. Role z `organizationRoles` dostaje tylko w danej organizacji.
 */
final readonly class InstallAccount
{
    /**
     * @param list<string> $roles
     * @param list<string> $organizations Klucze organizacji z plików instalacji, których konto jest członkiem (personel).
     * @param array<string, list<string>> $organizationRoles Klucz organizacji => role konta tylko w niej.
     * @param array<string, mixed> $attributes Atrybuty konta do `json_attributes` (np. identyfikatory zawodowe).
     * @param string|null $defaultOrganization Klucz organizacji z $organizations — domyślny kontekst konta po zalogowaniu
     *                                         (`defaults.organization`, Organizations\DefaultOrganization).
     */
    public function __construct(
        public string $email,
        public string $name,
        public array $roles,
        public bool $randomId,
        public array $organizations = [],
        public array $organizationRoles = [],
        public array $attributes = [],
        public ?string $defaultOrganization = null,
    ) {}

    /**
     * Role globalne konta (zespół spatie null).
     *
     * @return list<string>
     */
    public function globalRoles(): array
    {
        return $this->organizations === [] || in_array(RequiredRole::ROOT->value, $this->roles, true) ? $this->roles : [];
    }

    /**
     * Role konta w organizacji z pliku: `roles` (gdy nie są globalne) i role tej organizacji, bez powtórzeń.
     *
     * @return list<string>
     */
    public function rolesIn(string $organization): array
    {
        $shared = $this->globalRoles() === [] ? $this->roles : [];

        return array_values(array_unique([...$shared, ...$this->organizationRoles[$organization] ?? []]));
    }

    /**
     * Wszystkie nazwy ról z pliku (globalne i organizacji), do sprawdzenia, czy są zdefiniowane.
     *
     * @return list<string>
     */
    public function allRoles(): array
    {
        return array_values(array_unique([...$this->roles, ...array_merge(...array_values($this->organizationRoles))]));
    }
}
