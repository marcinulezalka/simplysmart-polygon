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

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\InvoiceStatus;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaClients;
use Simplysmart\Polygon\Integrations\Fakturownia\FakturowniaFailed;
use Simplysmart\Polygon\Integrations\Fakturownia\InvoiceMapper;
use Simplysmart\Polygon\Jobs\SendInvoiceJob;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\Invoice;
use Simplysmart\Polygon\Models\InvoicingAccount;

/**
 * Wysyłka faktury do Fakturowni (docs/baza-danych.md §21). Kolejność: kontrahent dostaje klienta w Fakturowni
 * (SyncContractors::push()), potem — przed każdą wysyłką, także ponowioną — szukamy faktury po naszym `oid` (token
 * faktury): znaleziona znaczy, że poprzednia próba doszła, więc tylko zapisujemy jej identyfikatory, bez drugiej
 * faktury. Odmowa z powodu zajętego `oid` (Fakturownia pilnuje `oid_unique`) kończy się tym samym odczytem. Po sukcesie
 * faktura ma `external_id`, `external_token`, numer, adres i stan „wysłana”. Błąd przejściowy (brak połączenia, limit,
 * błąd serwera) zostawia fakturę w kolejce i wraca wyjątkiem do ponowienia; inna odmowa daje stan „błąd wysyłki”
 * z komunikatem w `error`. resend() wraca fakturę z błędem do kolejki.
 */
final readonly class SendInvoice
{
    public function __construct(
        private InvoicingAccounts $accounts,
        private FakturowniaClients $clients,
        private SyncContractors $contractors,
    ) {}

    /**
     * @throws FakturowniaFailed Gdy błąd jest przejściowy i wysyłkę warto ponowić.
     */
    public function send(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canSend()) {
            return $invoice;
        }

        $account = $invoice->organization === null ? null : $this->accounts->of($invoice->organization);
        $contractor = $invoice->contractor;

        if ($account === null || $contractor === null) {
            return $this->fail($invoice, PackageText::get('invoicing.account_missing'));
        }

        try {
            $client = $this->clients->for($account);
            $link = $this->contractors->push($account, $contractor);
            $remote = $client->invoiceByOid($invoice->token);

            if ($remote === null) {
                try {
                    $remote = $client->createInvoice(InvoiceMapper::toPayload($invoice, $link->external_id, $account->department_id));
                } catch (FakturowniaFailed $failed) {
                    $remote = $failed->status === 422 ? $client->invoiceByOid($invoice->token) : null;

                    if ($remote === null) {
                        throw $failed;
                    }
                }
            }
        } catch (FakturowniaFailed $failed) {
            if ($failed->retryable()) {
                $invoice->forceFill([Invoice::ERROR => $failed->getMessage()])->save();

                throw $failed;
            }

            return $this->fail($invoice, $failed->getMessage());
        }

        return $this->sent($invoice, $account, $remote);
    }

    /**
     * Faktura z błędem wysyłki wraca do kolejki i dostaje nowe zadanie wysyłki.
     */
    public function resend(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Failed && $invoice->status !== InvoiceStatus::Queued) {
            return $invoice;
        }

        $invoice->forceFill([ColumnNames::STATUS => InvoiceStatus::Queued, Invoice::ERROR => null])->save();
        SendInvoiceJob::dispatch($invoice->id);

        return $invoice;
    }

    /**
     * @param array<array-key, mixed> $remote
     */
    private function sent(Invoice $invoice, InvoicingAccount $account, array $remote): Invoice
    {
        $number = InvoiceMapper::number($remote);
        $invoice->forceFill([
            ColumnNames::NAME => $number ?? $invoice->name,
            ColumnNames::STATUS => InvoiceStatus::Sent,
            Invoice::EXTERNAL_ID => InvoiceMapper::externalId($remote),
            Invoice::EXTERNAL_TOKEN => InvoiceMapper::externalToken($remote),
            Invoice::EXTERNAL_NUMBER => $number,
            Invoice::EXTERNAL_URL => InvoiceMapper::url($remote, $account->subdomain),
            Invoice::SENT_AT => CarbonImmutable::now(),
            Invoice::ERROR => null,
        ])->save();

        return $invoice;
    }

    private function fail(Invoice $invoice, string $message): Invoice
    {
        $invoice->forceFill([ColumnNames::STATUS => InvoiceStatus::Failed, Invoice::ERROR => $message])->save();

        return $invoice;
    }
}
