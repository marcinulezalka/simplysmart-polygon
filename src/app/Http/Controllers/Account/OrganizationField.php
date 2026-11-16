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
 * Pole formularzy kontekstu organizacji (przełączenie, organizacja domyślna): token aktywnej organizacji w polu NAME;
 * czy organizacja jest kontekstem konta, sprawdza kontroler, zgłaszając wtedy unavailable().
 */
final readonly class OrganizationField
{
    public const string NAME = 'organization';

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
     * Błąd pola: organizacja nie jest dostępna dla konta.
     */
    public static function unavailable(): ValidationException
    {
        return ValidationException::withMessages([self::NAME => text('account.organization.unavailable')]);
    }
}
