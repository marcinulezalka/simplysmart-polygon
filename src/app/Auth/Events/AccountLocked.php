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

namespace Simplysmart\Polygon\Auth\Events;

use Carbon\CarbonInterface;
use Simplysmart\Polygon\Contracts\SignInAccount;

/**
 * Konto zablokowane do `until` po błędnych PIN-ach (polygon wysyła e-mail i odwołuje zaufane urządzenia).
 */
final readonly class AccountLocked
{
    public function __construct(
        public string $zone,
        public SignInAccount $account,
        public CarbonInterface $until,
    ) {}
}
