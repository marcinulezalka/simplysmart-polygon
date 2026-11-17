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

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Queries\Organizations\ActiveOrganizations;

/**
 * Pola formularzy kontekstu organizacji (przełączenie, kontekst domyślny): token aktywnej organizacji w polu NAME
 * i opcjonalna rola w polu ROLE (strefa z kontekstem roli — `role_contexts`); czy para jest kontekstem konta, sprawdza
 * kontroler (Contracts\CurrentOrganization::offers()), zgłaszając wtedy unavailable().
 */
final readonly class OrganizationField
{
    public const string NAME = 'organization';

    public const string ROLE = 'role';

    private const int ROLE_MAX_LENGTH = 255;

    public function __construct(
        private ActiveOrganizations $organizations,
    ) {}

    /**
     * @throws ValidationException Gdy pola nie ma albo nie wskazuje aktywnej organizacji.
     */
    public function organization(Request $request): Organization
    {
        $validated = $request->validate([self::NAME => ['required', 'string', 'size:' . Organization::TOKEN_LENGTH]]);
        $token = $validated[self::NAME];

        return (is_string($token) ? $this->organizations->findByToken($token) : null) ?? throw self::unavailable();
    }

    /**
     * Rola z pola ROLE albo null (pole puste albo brak).
     *
     * @throws ValidationException Gdy pole nie jest tekstem albo jest za długie.
     */
    public function role(Request $request): ?string
    {
        $validated = $request->validate([self::ROLE => ['nullable', 'string', 'max:' . self::ROLE_MAX_LENGTH]]);
        $role = $validated[self::ROLE] ?? null;

        return is_string($role) && $role !== '' ? $role : null;
    }

    /**
     * Błąd pola: organizacja nie jest dostępna dla konta.
     */
    public static function unavailable(): ValidationException
    {
        return ValidationException::withMessages([self::NAME => text('account.organization.unavailable')]);
    }
}
