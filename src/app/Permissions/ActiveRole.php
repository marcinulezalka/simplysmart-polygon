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
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aktywna rola konta zalogowanego w żądaniu strefy z kontekstem roli (`role_contexts`, docs/strefy.md „Kontekst
 * organizacji”): w zespole spatie jego bieżącej organizacji relacja ról konta (Models\ZoneAccount::roles()) obejmuje
 * tylko tę rolę, więc can(), middleware uprawnień, hasRole() i getRoleNames() liczą jedną rolę zamiast sumy ról.
 * Ustawia ją i czyści middleware UseOrganizationPermissions; inne konta, inne zespoły i działania w bypass() widzą
 * wszystkie role. Stan żądania (wiązanie `scoped`).
 */
final class ActiveRole
{
    /** @var class-string<Model>|null */
    private ?string $model = null;

    private int|string|null $key = null;

    private ?int $organizationId = null;

    private ?string $role = null;

    private int $bypassed = 0;

    public function __construct(
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * Zawęża role konta w organizacji do jednej.
     */
    public function set(Model $account, int $organizationId, string $role): void
    {
        $key = $account->getKey();

        $this->model = $account::class;
        $this->key = is_int($key) || is_string($key) ? $key : null;
        $this->organizationId = $organizationId;
        $this->role = $role;
    }

    /**
     * Zdejmuje zawężenie (koniec żądania).
     */
    public function clear(): void
    {
        $this->model = null;
        $this->key = null;
        $this->organizationId = null;
        $this->role = null;
    }

    /**
     * Aktywna rola konta w bieżącym zespole spatie albo null — wszystkie role (inne konto, inny zespół, brak
     * zawężenia albo działanie w bypass()).
     */
    public function of(Model $account): ?string
    {
        if ($this->role === null || $this->bypassed > 0 || $account::class !== $this->model || $account->getKey() !== $this->key) {
            return null;
        }

        return $this->registrar->getPermissionsTeamId() === $this->organizationId ? $this->role : null;
    }

    /**
     * Uruchamia działanie bez zawężenia (np. lista wszystkich ról konta do przełącznika kontekstu, nadanie roli);
     * role wczytane wewnątrz trzeba wczytać od nowa po wyjściu (Permissions\OrganizationRoles::within() to robi).
     *
     * @template TResult
     *
     * @param Closure(): TResult $action
     *
     * @return TResult
     */
    public function bypass(Closure $action): mixed
    {
        $this->bypassed++;

        try {
            return $action();
        } finally {
            $this->bypassed--;
        }
    }
}
