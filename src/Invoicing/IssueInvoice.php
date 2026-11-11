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

use Illuminate\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\InvoiceStatus;
use Simplysmart\Polygon\Enums\InvoiceTax;
use Simplysmart\Polygon\Integrations\Fakturownia\TaxRateCode;
use Simplysmart\Polygon\Jobs\SendInvoiceJob;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\Contractor;
use Simplysmart\Polygon\Models\Invoice;
use Simplysmart\Polygon\Models\InvoiceItem;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\PolygonConfig;
use Throwable;

/**
 * Wystawienie faktury kontrahentowi przez organizację główną klienta (docs/baza-danych.md §21): w jednej transakcji
 * powstaje faktura z migawką sprzedawcy (organizacja główna) i nabywcy (kontrahent), pozycjami i sumami policzonymi
 * przez InvoiceAmounts, w stanie „w kolejce”, a po zatwierdzeniu transakcji zadanie Jobs\SendInvoiceJob wysyła ją do
 * systemu fakturowego. Numer nadaje Fakturownia — do wysyłki faktura ma numer roboczy. Wymagane: aktywne konto
 * organizacji, kontrahent z organizacji objętej kontem, co najmniej jedna pozycja z nazwą, ilością większą od zera,
 * ceną i stawką tłumaczalną na format Fakturowni, termin płatności nie wcześniejszy niż data wystawienia.
 * Odmowa to InvoicingRejected z polem formularza (pozycje: `items.<indeks>.<pole>`).
 */
final readonly class IssueInvoice
{
    /** Prefiks numeru roboczego faktury przed wysyłką. */
    public const string DRAFT_PREFIX = 'ROBOCZA/';

    public function __construct(
        private InvoicingAccounts $accounts,
        private ConnectionInterface $db,
        private Config $config,
    ) {}

    /**
     * @throws InvoicingRejected
     */
    public function issue(Organization $organization, Contractor $contractor, InvoiceData $data): Invoice
    {
        $account = $this->accounts->of($organization) ?? throw new InvoicingRejected('account', PackageText::get('invoicing.account_missing'));
        $seller = $this->accounts->root($organization);

        if (! in_array($contractor->organization_id, $this->accounts->organizationIds($account), true)) {
            throw new InvoicingRejected(Contractor::FOREIGN_KEY, PackageText::get('invoicing.contractor_foreign'));
        }

        if ($data->paymentDue !== null && $data->paymentDue->startOfDay()->lessThan($data->issueDate->startOfDay())) {
            throw new InvoicingRejected(Invoice::PAYMENT_DUE, PackageText::get('invoicing.payment_due_before_issue'));
        }

        $items = $this->items($data);
        $currency = strtoupper(trim($data->currencyCode ?? $this->config->string(PolygonConfig::CURRENCY)));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvoicingRejected(Invoice::CURRENCY_CODE, PackageText::get('invoicing.currency_invalid'));
        }

        $invoice = $this->db->transaction(function () use ($seller, $contractor, $data, $items, $currency): Invoice {
            $invoice = new Invoice();
            $invoice->forceFill([
                Organization::FOREIGN_KEY => $seller->id,
                Contractor::FOREIGN_KEY => $contractor->id,
                ColumnNames::NAME => self::DRAFT_PREFIX . $data->issueDate->format('Ymd') . '/' . strtoupper(bin2hex(random_bytes(3))),
                ColumnNames::STATUS => InvoiceStatus::Queued,
                Invoice::KIND => $data->kind,
                Invoice::ISSUE_DATE => $data->issueDate->toDateString(),
                Invoice::SELL_DATE => $data->sellDate->toDateString(),
                Invoice::PAYMENT_DUE => $data->paymentDue?->toDateString(),
                Invoice::PAYMENT_METHOD => $data->paymentMethod,
                Invoice::CURRENCY_CODE => $currency,
                Invoice::TOTAL_NET => InvoiceAmounts::sum(array_map(static fn(array $item): string => $item['amounts']['net'], $items)),
                Invoice::TOTAL_VAT => InvoiceAmounts::sum(array_map(static fn(array $item): string => $item['amounts']['vat'], $items)),
                Invoice::TOTAL_GROSS => InvoiceAmounts::sum(array_map(static fn(array $item): string => $item['amounts']['gross'], $items)),
                Invoice::SELLER_NAME => $seller->name,
                Invoice::SELLER_NIP => $seller->nip,
                Invoice::SELLER_STREET => $seller->street,
                Invoice::SELLER_POSTAL_CODE => $seller->postal_code,
                Invoice::SELLER_CITY => $seller->city,
                Invoice::SELLER_COUNTRY_CODE => $seller->country_code,
                Invoice::BUYER_NAME => $contractor->name,
                Invoice::BUYER_NIP => $contractor->nip,
                Invoice::BUYER_STREET => $contractor->street,
                Invoice::BUYER_POSTAL_CODE => $contractor->postal_code,
                Invoice::BUYER_CITY => $contractor->city,
                Invoice::BUYER_COUNTRY_CODE => $contractor->country_code,
                Invoice::NOTES => trim((string) $data->notes) === '' ? null : trim((string) $data->notes),
            ])->save();

            foreach ($items as $index => $item) {
                $model = new InvoiceItem();
                $model->forceFill([
                    Invoice::FOREIGN_KEY => $invoice->id,
                    ColumnNames::SORT_ORDER => $index,
                    ...$item['attributes'],
                    InvoiceItem::NET => $item['amounts']['net'],
                    InvoiceItem::VAT => $item['amounts']['vat'],
                    InvoiceItem::GROSS => $item['amounts']['gross'],
                ])->save();
            }

            return $invoice;
        });

        SendInvoiceJob::dispatch($invoice->id);

        return $invoice;
    }

    /**
     * Pozycje jako atrybuty modelu i policzone kwoty.
     *
     * @return list<array{attributes: array<string, mixed>, amounts: array{net: numeric-string, vat: numeric-string, gross: numeric-string}}>
     *
     * @throws InvoicingRejected
     */
    private function items(InvoiceData $data): array
    {
        if ($data->items === []) {
            throw new InvoicingRejected('items', PackageText::get('invoicing.items_required'));
        }

        $items = [];

        foreach ($data->items as $index => $item) {
            $field = static fn(string $name): string => 'items.' . $index . '.' . $name;
            $name = trim($item->name);
            $quantity = InvoiceAmounts::decimal($item->quantity);
            $price = InvoiceAmounts::decimal($item->unitPriceNet);
            $percent = $item->tax->hasPercentage() ? InvoiceAmounts::decimal($item->taxPercent) : null;

            if ($name === '') {
                throw new InvoicingRejected($field(ColumnNames::NAME), PackageText::get('invoicing.item_name_required'));
            }

            if ($quantity === null || bccomp($quantity, '0', 6) !== 1) {
                throw new InvoicingRejected($field(InvoiceItem::QUANTITY), PackageText::get('invoicing.item_quantity_invalid'));
            }

            if ($price === null) {
                throw new InvoicingRejected($field(InvoiceItem::UNIT_PRICE_NET), PackageText::get('invoicing.item_price_invalid'));
            }

            try {
                TaxRateCode::of($item->tax, $percent);
            } catch (Throwable) {
                throw new InvoicingRejected($field(InvoiceItem::TAX_PERCENT), PackageText::get('invoicing.item_tax_invalid'));
            }

            $unit = trim((string) $item->unit);
            $items[] = [
                'attributes' => [
                    ColumnNames::NAME => $name,
                    InvoiceItem::QUANTITY => $quantity,
                    InvoiceItem::UNIT => $unit === '' ? null : $unit,
                    InvoiceItem::UNIT_PRICE_NET => $price,
                    InvoiceItem::TAX_RATE_ID => $item->taxRateId,
                    InvoiceItem::TAX => $item->tax,
                    InvoiceItem::TAX_PERCENT => $item->tax === InvoiceTax::Rate ? $percent : null,
                ],
                'amounts' => InvoiceAmounts::of($quantity, $price, $item->tax, $percent),
            ];
        }

        return $items;
    }
}
