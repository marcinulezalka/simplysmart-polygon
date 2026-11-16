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

namespace Simplysmart\Polygon\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Potwierdzenie resetu PIN-u dla właściciela konta: PIN usunięty, wszystkie sesje wylogowane, nowy PIN przy następnym
 * logowaniu kodem z e-maila (ostrzega, jeśli to nie on). Szablon strefy `pin-reset` (`accountName`, `resetAt`).
 */
final class PinResetMail extends ZoneMailable implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public function __construct(
        string $zoneName,
        public readonly string $accountName,
        public readonly CarbonInterface $resetAt,
    ) {
        parent::__construct($zoneName);
    }

    protected function template(): string
    {
        return 'pin-reset';
    }

    protected function subjectKey(): string
    {
        return 'pin_reset.subject';
    }
}
