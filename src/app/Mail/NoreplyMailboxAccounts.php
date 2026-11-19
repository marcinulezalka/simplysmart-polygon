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
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Queries\Organizations\OrganizationUnits;

/**
 * Konta nadawcy „noreply” (docs/baza-danych.md §22). Konto należy do organizacji głównej klienta — najwyższej nad
 * daną organizacją, bez organizacji hero (root()) — tak jak konto Fakturowni (Invoicing\InvoicingAccounts): of()
 * daje aktywne nadpisanie (host podany) dla dowolnej organizacji drzewa, configured() — wiersz nawet z pustym
 * `host` (ekran ustawień, jak Mailbox\MailboxAccounts::configured() w ulmed).
 */
final readonly class NoreplyMailboxAccounts
{
    public function __construct(
        private OrganizationUnits $units,
    ) {}

    public function root(Organization $organization): Organization
    {
        return $this->units->root($organization);
    }

    /**
     * Konto organizacji głównej, także z wyłączonym nadpisaniem (`host` pusty) — ekran ustawień.
     */
    public function configured(Organization $organization): ?NoreplyMailboxAccount
    {
        return NoreplyMailboxAccount::query()->where(Organization::FOREIGN_KEY, $this->root($organization)->id)->first();
    }

    /**
     * Konto z aktywnym nadpisaniem (`host` podany) — dla wysyłki (Mail\OrganizationMailer).
     */
    public function of(Organization $organization): ?NoreplyMailboxAccount
    {
        $account = $this->configured($organization);

        return $account !== null && $account->overridesMail() ? $account : null;
    }
}
