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

namespace Simplysmart\Polygon\Integrations\Fakturownia;

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\InvoiceKind;
use Simplysmart\Polygon\Enums\InvoiceSource;
use Simplysmart\Polygon\Enums\InvoiceStatus;
use Simplysmart\Polygon\Enums\PaymentMethod;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\Models\Invoice;
use Simplysmart\Polygon\Models\InvoiceItem;
use Throwable;

/**
 * Faktura z odpowiedzi API Fakturowni (lista `invoices.json` z `include_positions`) jako kolumny `invoices`
 * i `invoice_items` — odwrotność InvoiceMapper. Kwoty są brane z Fakturowni bez przeliczania (sumy faktury, a gdy ich
 * brak — suma pozycji); VAT pozycji to brutto minus netto. Stawka pozycji przez TaxRateCode::parse(). Forma płatności
 * spoza Enums\PaymentMethod to przelew. Teksty dłuższe niż kolumny są przycinane. Faktura innego rodzaju niż VAT
 * i pro forma, bez id, bez daty wystawienia albo z pozycją o nieznanej stawce jest odrzucana (RemoteInvoiceRejected
 * z powodem do raportu).
 */
final class RemoteInvoiceMapper
{
    /** Zapis daty w API. */
    private const string DATE_FORMAT = 'Y-m-d';

    /** Długości kolumn tekstowych `invoices` (nazwa i numer, NIP, kod pocztowy, identyfikatory zewnętrzne). */
    private const array LENGTHS = [
        ColumnNames::NAME => 255,
        Invoice::EXTERNAL_NUMBER => 64,
        Invoice::EXTERNAL_TOKEN => 64,
        Invoice::EXTERNAL_STATUS => 32,
        'nip' => 32,
        'street' => 255,
        'postal_code' => 16,
        'city' => 255,
        InvoiceItem::UNIT => 16,
    ];

    /** Pola strony faktury w API (bez prefiksu `seller_` / `buyer_`) dla kolumn migawki. */
    private const array PARTY_FIELDS = ['name' => 'name', 'nip' => 'tax_no', 'street' => 'street', 'postal_code' => 'post_code', 'city' => 'city', 'country_code' => 'country'];

    /**
     * @param array<array-key, mixed> $remote
     *
     * @throws RemoteInvoiceRejected
     */
    public static function read(array $remote, string $subdomain): RemoteInvoice
    {
        $externalId = self::text($remote, 'id');
        $kind = InvoiceKind::tryFrom((string) self::text($remote, 'kind'));
        $issueDate = self::date($remote, 'issue_date');

        if ($externalId === null) {
            throw new RemoteInvoiceRejected(PackageText::get('invoicing.invoice_without_id'));
        }

        if ($kind === null) {
            throw new RemoteInvoiceRejected(PackageText::get('invoicing.invoice_kind_unsupported', ['kind' => (string) self::text($remote, 'kind')]));
        }

        if ($issueDate === null) {
            throw new RemoteInvoiceRejected(PackageText::get('invoicing.invoice_without_date'));
        }

        $items = self::items($remote);
        $number = self::cut(self::text($remote, 'number'), ColumnNames::NAME);
        $attributes = [
            ColumnNames::NAME => $number ?? '#' . $externalId,
            ColumnNames::STATUS => InvoiceStatus::Sent,
            Invoice::KIND => $kind,
            Invoice::SOURCE => InvoiceSource::Fakturownia,
            Invoice::ISSUE_DATE => $issueDate->toDateString(),
            Invoice::SELL_DATE => (self::date($remote, 'sell_date') ?? $issueDate)->toDateString(),
            Invoice::PAYMENT_DUE => self::date($remote, 'payment_to')?->toDateString(),
            Invoice::PAYMENT_METHOD => PaymentMethod::tryFrom((string) self::text($remote, 'payment_type')) ?? PaymentMethod::Transfer,
            Invoice::CURRENCY_CODE => self::currency($remote),
            Invoice::TOTAL_NET => self::amount($remote, 'price_net') ?? self::sum($items, InvoiceItem::NET),
            Invoice::TOTAL_VAT => self::amount($remote, 'price_tax') ?? self::sum($items, InvoiceItem::VAT),
            Invoice::TOTAL_GROSS => self::amount($remote, 'price_gross') ?? self::sum($items, InvoiceItem::GROSS),
            Invoice::EXTERNAL_ID => $externalId,
            Invoice::EXTERNAL_TOKEN => self::cut(self::text($remote, 'token'), Invoice::EXTERNAL_TOKEN),
            Invoice::EXTERNAL_NUMBER => self::cut($number, Invoice::EXTERNAL_NUMBER),
            Invoice::EXTERNAL_URL => InvoiceMapper::url($remote, $subdomain),
            Invoice::NOTES => self::text($remote, 'description'),
            ...self::status($remote),
        ];

        foreach (['seller', 'buyer'] as $party) {
            foreach (self::PARTY_FIELDS as $column => $field) {
                $value = self::text($remote, $party . '_' . $field);
                $attributes[$party . '_' . $column] = $column === 'country_code' ? self::country($value) : self::cut($value, $column);
            }
        }

        $clientId = self::text($remote, 'client_id');

        return new RemoteInvoice(
            externalId: $externalId,
            clientId: $clientId !== null && ctype_digit($clientId) && (int) $clientId > 0 ? $clientId : null,
            oid: self::text($remote, 'oid'),
            updatedAt: self::moment($remote, 'updated_at'),
            buyer: array_filter([
                'name' => self::text($remote, 'buyer_name'),
                'tax_no' => self::text($remote, 'buyer_tax_no'),
                'street' => self::text($remote, 'buyer_street'),
                'post_code' => self::text($remote, 'buyer_post_code'),
                'city' => self::text($remote, 'buyer_city'),
                'country' => self::text($remote, 'buyer_country'),
                'email' => self::text($remote, 'buyer_email'),
                'phone' => self::text($remote, 'buyer_phone'),
            ], static fn(?string $value): bool => $value !== null),
            attributes: $attributes,
            items: $items,
        );
    }

    /**
     * Kolumny stanu faktury w Fakturowni — odświeżane także dla faktur wystawionych w aplikacji.
     *
     * @param array<array-key, mixed> $remote
     *
     * @return array<string, mixed>
     */
    public static function status(array $remote): array
    {
        return [
            Invoice::EXTERNAL_STATUS => self::cut(self::text($remote, 'status'), Invoice::EXTERNAL_STATUS),
            Invoice::EXTERNAL_UPDATED_AT => self::moment($remote, 'updated_at'),
            Invoice::PAID_AT => self::date($remote, 'paid_date')?->toDateString(),
        ];
    }

    /**
     * @param array<array-key, mixed> $remote
     *
     * @return list<array<string, mixed>>
     *
     * @throws RemoteInvoiceRejected
     */
    private static function items(array $remote): array
    {
        $positions = $remote['positions'] ?? [];
        $items = [];

        foreach (is_array($positions) ? array_values(array_filter($positions, is_array(...))) : [] as $index => $position) {
            $tax = TaxRateCode::parse($position['tax'] ?? null);
            $name = self::cut(self::text($position, 'name'), ColumnNames::NAME) ?? '—';

            if ($tax === null) {
                throw new RemoteInvoiceRejected(PackageText::get('invoicing.invoice_tax_unknown', ['item' => $name, 'tax' => (string) self::text($position, 'tax')]));
            }

            $quantity = self::decimal(self::text($position, 'quantity'), 3) ?? '1.000';
            $net = self::amount($position, 'total_price_net') ?? '0.00';
            $gross = self::amount($position, 'total_price_gross') ?? $net;
            $items[] = [
                ColumnNames::NAME => $name,
                ColumnNames::SORT_ORDER => $index,
                InvoiceItem::QUANTITY => $quantity,
                InvoiceItem::UNIT => self::cut(self::text($position, 'quantity_unit'), InvoiceItem::UNIT),
                InvoiceItem::UNIT_PRICE_NET => self::amount($position, 'price_net') ?? '0.00',
                InvoiceItem::TAX => $tax[0],
                InvoiceItem::TAX_PERCENT => $tax[1],
                InvoiceItem::NET => $net,
                InvoiceItem::VAT => bcsub($gross, $net, 2),
                InvoiceItem::GROSS => $gross,
            ];
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return numeric-string
     */
    private static function sum(array $items, string $column): string
    {
        $sum = '0.00';

        foreach ($items as $item) {
            $value = $item[$column] ?? '0';
            $sum = bcadd($sum, is_string($value) && is_numeric($value) ? $value : '0', 2);
        }

        return $sum;
    }

    /**
     * Kwota do groszy (także ujemna — rabat) albo null.
     *
     * @param array<array-key, mixed> $data
     *
     * @return numeric-string|null
     */
    private static function amount(array $data, string $key): ?string
    {
        return self::decimal(self::text($data, $key), 2);
    }

    /**
     * @return numeric-string|null
     */
    private static function decimal(?string $value, int $scale): ?string
    {
        $value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $value);

        if (preg_match('/^-?\d{1,12}(\.\d+)?$/', $value) !== 1 || ! is_numeric($value)) {
            return null;
        }

        $half = bcdiv('5', bcpow('10', (string) ($scale + 1)), $scale + 1);

        return bcadd($value, str_starts_with($value, '-') ? bcmul($half, '-1', $scale + 1) : $half, $scale);
    }

    /**
     * @param array<array-key, mixed> $remote
     */
    private static function currency(array $remote): string
    {
        $currency = strtoupper((string) self::text($remote, 'currency'));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'PLN';
    }

    private static function country(?string $value): ?string
    {
        return $value !== null && preg_match('/^[A-Za-z]{2}$/', $value) === 1 ? strtoupper($value) : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function date(array $data, string $key): ?CarbonImmutable
    {
        $value = self::text($data, $key);

        if ($value === null) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat(self::DATE_FORMAT, substr($value, 0, 10));

        return $date === null ? null : $date->startOfDay();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function moment(array $data, string $key): ?CarbonImmutable
    {
        $value = self::text($data, $key);

        try {
            return $value === null ? null : CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function cut(?string $value, string $column): ?string
    {
        $length = self::LENGTHS[$column] ?? 255;

        return $value === null ? null : rtrim(mb_substr($value, 0, $length));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function text(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        $value = is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}
