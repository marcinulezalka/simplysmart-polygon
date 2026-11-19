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

use Simplysmart\Polygon\Models\NoreplyMailboxAccount;

/**
 * Wysyłanie przez nadawcę „noreply” organizacji: prawdziwy serwer SMTP — Mail\SmtpNoreplyMailer (bound w
 * Providers\MailServiceProvider); w testach podmieniane na dowolną implementację (bez prawdziwego serwera SMTP),
 * jak Ulmed\Mailbox\MailboxMailer.
 */
interface NoreplyMailer
{
    /**
     * @throws NoreplyMailerException
     */
    public function send(NoreplyMailboxAccount $account, string $to, string $subject, string $body): void;
}
