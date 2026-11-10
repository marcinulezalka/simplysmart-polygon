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

use Simplysmart\Polygon\Contracts\SignInAccount;

/**
 * Właściciel zresetował PIN na stronie „Bezpieczeństwo” (Auth\ResetOwnPin); polygon wysyła mu potwierdzenie z dalszymi
 * krokami (nowy PIN przy następnym logowaniu).
 */
final readonly class PinReset
{
    public function __construct(
        public string $zone,
        public SignInAccount $account,
        public ?string $ip,
    ) {}
}
