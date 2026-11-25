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

namespace Simplysmart\Polygon\Models;

use Simplysmart\Polygon\Models\Relations\BelongsToOrganization;

/**
 * Nadawca SMTP „noreply” organizacji głównej (`noreply_mailbox_accounts`, docs/baza-danych.md §22): nadpisuje
 * ustawienia SMTP z `.env` dla poczty wysyłanej w imieniu tej organizacji, gdy skonfigurowany — host, port,
 * szyfrowanie, login, hasło (szyfrowane, cast `encrypted`; nigdy nie trafia do widoków ani logów) i adres nadawcy
 * (`from_address`, np. `noreply@klinika.pl`). Puste `host` — nadpisanie wyłączone, mimo istniejącego wiersza
 * (overridesMail()); czyszczenie `host` jest jedynym sposobem wyłączenia —
 * nie ma osobnej akcji rozłączenia. `name` — etykieta konta. Zapisywane przez Mail\SaveNoreplyMailboxAccount,
 * wysyła Mail\NoreplyMailer, rozwiązuje Mail\OrganizationMailer.
 *
 * @property int $organization_id
 * @property string|null $host
 * @property int|null $port
 * @property string|null $encryption
 * @property string|null $username
 * @property string|null $password
 * @property string|null $from_address
 * @property string|null $last_error
 * @property-read Organization|null $organization
 */
final class NoreplyMailboxAccount extends DataModel
{
    use BelongsToOrganization;

    public const string TABLE = 'noreply_mailbox_accounts';

    public const string HOST = 'host';

    public const string PORT = 'port';

    public const string ENCRYPTION = 'encryption';

    public const string USERNAME = 'username';

    public const string PASSWORD = 'password';

    public const string FROM_ADDRESS = 'from_address';

    public const string LAST_ERROR = 'last_error';

    protected $table = self::TABLE;

    /**
     * @var list<string>
     */
    protected $hidden = [self::PASSWORD];

    /**
     * Nadpisanie SMTP z `.env` włączone — host podany, niezależnie od reszty pól.
     */
    public function overridesMail(): bool
    {
        return $this->host !== null && $this->host !== '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            Organization::FOREIGN_KEY => 'integer',
            self::HOST => 'string',
            self::PORT => 'integer',
            self::ENCRYPTION => 'string',
            self::USERNAME => 'string',
            self::PASSWORD => 'encrypted',
            self::FROM_ADDRESS => 'string',
            self::LAST_ERROR => 'string',
        ];
    }
}
