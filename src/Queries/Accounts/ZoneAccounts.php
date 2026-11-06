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

namespace Simplysmart\Polygon\Queries\Accounts;

use Simplysmart\Polygon\Auth\AccountColumns;
use Simplysmart\Polygon\Auth\EmailIndex;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Odczyt kont strefy: konto po adresie e-mail (przez `email_hash`, także usunięte — adres usuniętego konta zostaje
 * zajęty).
 */
final readonly class ZoneAccounts
{
    public function __construct(
        private EmailIndex $emails,
    ) {}

    public function findByEmail(ZoneDefinition $zone, string $email): ?ZoneAccount
    {
        $model = $zone->accountModel();

        return $model::withTrashed()->where(AccountColumns::EMAIL_HASH, $this->emails->of($email))->first();
    }
}
