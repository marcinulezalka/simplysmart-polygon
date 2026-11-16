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
use Illuminate\Support\Carbon;
use Simplysmart\Polygon\Auth\Events\PinReset;
use Simplysmart\Polygon\Mail\PinResetMail;

/**
 * Potwierdzenie resetu PIN-u dla właściciela konta (zdarzenie PinReset, e-mail w kolejce).
 */
final readonly class SendPinResetMail
{
    public function __construct(
        private Mailer $mailer,
    ) {}

    public function handle(PinReset $event): void
    {
        $this->mailer->to($event->account->emailAddress())->queue(new PinResetMail($event->zone, $event->account->displayName(), Carbon::now()));
    }
}
