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

use Illuminate\Mail\MailManager;
use Illuminate\Mail\Message;
use Simplysmart\Polygon\Models\NoreplyMailboxAccount;
use Throwable;
use Simplysmart\Polygon\Enums\MailEncryption;

/**
 * Wysyłanie przez prawdziwy serwer SMTP skonfigurowany jako nadawca „noreply” organizacji (MailManager::build() —
 * mailer złożony z podanej konfiguracji, bez rejestrowania w config('mail.mailers')).
 */
final readonly class SmtpNoreplyMailer implements NoreplyMailer
{
    public function __construct(
        private MailManager $manager,
    ) {}

    public function send(NoreplyMailboxAccount $account, string $to, string $subject, string $body): void
    {
        if (! $account->overridesMail()) {
            throw new NoreplyMailerException('Nadawca „noreply” nie jest skonfigurowany.');
        }

        try {
            $mailer = $this->manager->build([
                'transport' => 'smtp',
                'host' => $account->host,
                'port' => $account->port,
                'scheme' => MailEncryption::smtpScheme($account->encryption),
                'username' => $account->username,
                'password' => $account->password,
            ]);

            $mailer->raw($body, function (Message $message) use ($account, $to, $subject): void {
                $message->to($to)->subject($subject)->from($account->from_address ?? $account->username ?? '');
            });
        } catch (Throwable $exception) {
            throw new NoreplyMailerException('Nie udało się wysłać wiadomości.', previous: $exception);
        }
    }
}
