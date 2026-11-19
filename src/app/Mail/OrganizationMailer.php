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

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Simplysmart\Polygon\Models\Organization;

/**
 * Mailer organizacji: zbudowany z jej nadawcy „noreply” (NoreplyMailboxAccounts::of()), gdy skonfigurowany —
 * inaczej `null`, wywołujący wraca do mailera domyślnego (config('mail.default'), `.env`). Nie mutuje
 * config('mail.*') — MailManager::build() składa mailer z podanej konfiguracji bez rejestrowania go.
 *
 * Zasięg: poczta wysyłana w imieniu organizacji (np. powiadomienia do jej pacjentów). Poczta systemowa konta strefy
 * (reset PIN, zablokowane konto — Mail\PinResetMail i pokrewne) nie przechodzi przez ten resolver — nie jest
 * związana z żadną organizacją.
 */
final readonly class OrganizationMailer
{
    public function __construct(
        private NoreplyMailboxAccounts $accounts,
        private MailManager $manager,
    ) {}

    public function mailerFor(Organization $organization): ?Mailer
    {
        $account = $this->accounts->of($organization);

        if ($account === null) {
            return null;
        }

        return $this->manager->build([
            'transport' => 'smtp',
            'host' => $account->host,
            'port' => $account->port,
            'scheme' => $account->encryption === 'ssl' ? 'smtps' : 'smtp',
            'username' => $account->username,
            'password' => $account->password,
        ]);
    }
}
