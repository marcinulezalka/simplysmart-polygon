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

namespace Simplysmart\Polygon\Invoicing;

use InvalidArgumentException;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\InvoicingProvider;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaClients;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\InvoicingAccount;
use Simplysmart\Polygon\Models\Organization;

/**
 * Zapis konta Fakturowni organizacji głównej (InvoicingAccounts::root()): subdomena (sama nazwa konta — z wklejonego
 * adresu zostaje nazwa), token API (pusty zostawia zapisany; nowe konto go wymaga) i opcjonalny dział wystawcy.
 * Dane są zapisywane dopiero po udanym połączeniu z kontem (FakturowniaClient::account()), więc zapisane konto działa.
 * Odmowa to InvoicingRejected z polem formularza; treść błędu nie zawiera tokenu.
 */
final readonly class SaveInvoicingAccount
{
    public function __construct(
        private InvoicingAccounts $accounts,
        private FakturowniaClients $clients,
    ) {}

    /**
     * @throws InvoicingRejected Gdy subdomena jest błędna, brakuje tokenu albo Fakturownia odrzuca dane dostępowe.
     */
    public function save(Organization $organization, string $subdomain, ?string $token, ?string $departmentId = null, bool $active = true): InvoicingAccount
    {
        $root = $this->accounts->root($organization);
        $account = $this->accounts->configured($root) ?? new InvoicingAccount();
        $subdomain = FakturowniaClients::subdomain($subdomain);
        $token = trim((string) $token);
        $token = $token === '' ? ($account->exists ? $account->api_token : '') : $token;
        $departmentId = trim((string) $departmentId);

        if ($token === '') {
            throw new InvoicingRejected(InvoicingAccount::API_TOKEN, PackageText::get('invoicing.token_required'));
        }

        if ($departmentId !== '' && preg_match('/^\d{1,18}$/', $departmentId) !== 1) {
            throw new InvoicingRejected(InvoicingAccount::DEPARTMENT_ID, PackageText::get('invoicing.department_invalid'));
        }

        try {
            $this->clients->with($subdomain, $token)->account();
        } catch (InvalidArgumentException) {
            throw new InvoicingRejected(InvoicingAccount::SUBDOMAIN, PackageText::get('invoicing.subdomain_invalid'));
        } catch (FakturowniaFailed $failed) {
            throw new InvoicingRejected(
                $failed->unauthorized() ? InvoicingAccount::API_TOKEN : InvoicingAccount::SUBDOMAIN,
                PackageText::get($failed->unauthorized() ? 'invoicing.unauthorized' : 'invoicing.connection_failed', ['reason' => $failed->getMessage()]),
            );
        }

        $account->forceFill([
            Organization::FOREIGN_KEY => $root->id,
            ColumnNames::NAME => InvoicingProvider::Fakturownia->label() . ' — ' . $subdomain,
            ColumnNames::IS_ACTIVE => $active,
            InvoicingAccount::PROVIDER => InvoicingProvider::Fakturownia,
            InvoicingAccount::SUBDOMAIN => $subdomain,
            InvoicingAccount::API_TOKEN => $token,
            InvoicingAccount::DEPARTMENT_ID => $departmentId === '' ? null : $departmentId,
            InvoicingAccount::LAST_ERROR => null,
        ])->save();

        return $account;
    }
}
