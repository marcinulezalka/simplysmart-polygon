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

use Simplysmart\Polygon\Auth\AccessRevocationReason;
use Simplysmart\Polygon\Auth\AccountActor;
use Simplysmart\Polygon\Contracts\SignInAccount;

/**
 * Wszystkie sesje konta zostały unieważnione (nowa epoka); polygon odwołuje też jego zaufane urządzenia.
 */
final readonly class AccountAccessRevoked
{
    public function __construct(
        public string $zone,
        public SignInAccount $account,
        public AccessRevocationReason $reason,
        public ?AccountActor $actor = null,
    ) {}
}
