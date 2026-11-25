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

use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\NoreplyMailboxAccount;
use Simplysmart\Polygon\Models\Organization;

/**
 * Zapis nadawcy „noreply” organizacji głównej (NoreplyMailboxAccounts::root()): jeden wiersz na organizację,
 * kolejny zapis aktualizuje istniejący. Hasło nadpisywane tylko gdy podane — formularz nie pokazuje zapisanego
 * hasła ponownie, puste pole zachowuje poprzednie. Bez wiersza, czyszczenie
 * `host` wyłącza nadpisanie (NoreplyMailboxAccount::overridesMail()) — bez eager-check połączenia przy zapisie, w
 * przeciwieństwie do Invoicing\SaveInvoicingAccount: weryfikacja jest osobną akcją (test wysyłki).
 */
final readonly class SaveNoreplyMailboxAccount
{
    public function __construct(
        private NoreplyMailboxAccounts $accounts,
    ) {}

    /**
     * @param array{host: ?string, port: ?int, encryption: ?string, username: ?string, password: ?string, from_address: ?string} $data
     */
    public function save(Organization $organization, array $data): NoreplyMailboxAccount
    {
        $root = $this->accounts->root($organization);
        $account = $this->accounts->configured($root) ?? new NoreplyMailboxAccount();

        $account->forceFill([
            Organization::FOREIGN_KEY => $root->id,
            ColumnNames::NAME => $data['from_address'] ?? $data['host'] ?? 'noreply',
            ColumnNames::IS_ACTIVE => true,
            NoreplyMailboxAccount::HOST => $data['host'],
            NoreplyMailboxAccount::PORT => $data['port'],
            NoreplyMailboxAccount::ENCRYPTION => $data['encryption'],
            NoreplyMailboxAccount::USERNAME => $data['username'],
            NoreplyMailboxAccount::FROM_ADDRESS => $data['from_address'],
            NoreplyMailboxAccount::LAST_ERROR => null,
        ]);

        if ($data['password'] !== null && $data['password'] !== '') {
            $account->forceFill([NoreplyMailboxAccount::PASSWORD => $data['password']]);
        }

        $account->save();

        return $account;
    }

    /**
     * Zapisuje błąd testowej wysyłki (ekran ustawień paczki strefy).
     */
    public function recordError(NoreplyMailboxAccount $account, ?string $error): void
    {
        $account->forceFill([NoreplyMailboxAccount::LAST_ERROR => $error])->save();
    }
}
