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

namespace Simplysmart\Polygon\Auth\Listeners;

use Illuminate\Contracts\Mail\Mailer;
use Simplysmart\Polygon\Auth\Events\AccountLocked;
use Simplysmart\Polygon\Mail\AccountLockedMail;

/**
 * Powiadomienie właściciela o blokadzie konta po błędnych PIN-ach (zdarzenie AccountLocked, e-mail w kolejce).
 */
final readonly class SendAccountLockedMail
{
    public function __construct(
        private Mailer $mailer,
    ) {}

    public function handle(AccountLocked $event): void
    {
        $this->mailer->to($event->account->emailAddress())->queue(new AccountLockedMail($event->zone, $event->account->displayName(), $event->until));
    }
}
