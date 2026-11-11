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

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\InvoiceKind;
use Simplysmart\Polygon\Enums\InvoiceStatus;
use Simplysmart\Polygon\Enums\PaymentMethod;
use Simplysmart\Polygon\Models\Relations\BelongsToOrganization;

/**
 * Faktura wystawiona przez organizację kontrahentowi (`invoices`, docs/baza-danych.md §21). Powstaje w aplikacji
 * (Invoicing\IssueInvoice) i jest wysyłana do systemu fakturowego (Invoicing\SendInvoice), który nadaje numer:
 * `name` — numer faktury (przed wysyłką roboczy, numer()), `kind`, `status` (Enums\InvoiceStatus — stan dziedzinowy
 * zamiast stanu wiersza), daty `issue_date`, `sell_date`, `payment_due`, `payment_method`, `currency_code`, sumy
 * `total_net`, `total_vat`, `total_gross` oraz migawka sprzedawcy (`seller_*`, z organizacji) i nabywcy (`buyer_*`,
 * z kontrahenta) z dnia wystawienia. Identyfikatory zewnętrzne: `external_id` (numeryczne id w Fakturowni — adresy
 * API i PDF), `external_token` (token faktury w Fakturowni — publiczny link), `external_number`, `external_url`;
 * własny `token` faktury jest wysyłany jako `oid` — klucz, po którym wysyłka wykrywa duplikat. `sent_at` — chwila
 * wysłania, `error` — komunikat ostatniego błędu wysyłki, `notes` — uwagi na fakturze.
 *
 * @property int $organization_id
 * @property int $contractor_id
 * @property InvoiceKind $kind
 * @property InvoiceStatus $status
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable $sell_date
 * @property CarbonImmutable|null $payment_due
 * @property PaymentMethod $payment_method
 * @property string $currency_code
 * @property string $total_net
 * @property string $total_vat
 * @property string $total_gross
 * @property string|null $seller_name
 * @property string|null $seller_nip
 * @property string|null $seller_street
 * @property string|null $seller_postal_code
 * @property string|null $seller_city
 * @property string|null $seller_country_code
 * @property string|null $buyer_name
 * @property string|null $buyer_nip
 * @property string|null $buyer_street
 * @property string|null $buyer_postal_code
 * @property string|null $buyer_city
 * @property string|null $buyer_country_code
 * @property string|null $external_id
 * @property string|null $external_token
 * @property string|null $external_number
 * @property string|null $external_url
 * @property CarbonImmutable|null $sent_at
 * @property string|null $error
 * @property string|null $notes
 * @property-read Organization|null $organization
 * @property-read Contractor|null $contractor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InvoiceItem> $items
 */
final class Invoice extends DataModel
{
    use BelongsToOrganization;

    public const string TABLE = 'invoices';

    /** Klucz obcy faktury w tabeli pozycji. */
    public const string FOREIGN_KEY = 'invoice_id';

    public const string KIND = 'kind';

    public const string ISSUE_DATE = 'issue_date';

    public const string SELL_DATE = 'sell_date';

    public const string PAYMENT_DUE = 'payment_due';

    public const string PAYMENT_METHOD = 'payment_method';

    public const string CURRENCY_CODE = 'currency_code';

    public const string TOTAL_NET = 'total_net';

    public const string TOTAL_VAT = 'total_vat';

    public const string TOTAL_GROSS = 'total_gross';

    public const string SELLER_NAME = 'seller_name';

    public const string SELLER_NIP = 'seller_nip';

    public const string SELLER_STREET = 'seller_street';

    public const string SELLER_POSTAL_CODE = 'seller_postal_code';

    public const string SELLER_CITY = 'seller_city';

    public const string SELLER_COUNTRY_CODE = 'seller_country_code';

    public const string BUYER_NAME = 'buyer_name';

    public const string BUYER_NIP = 'buyer_nip';

    public const string BUYER_STREET = 'buyer_street';

    public const string BUYER_POSTAL_CODE = 'buyer_postal_code';

    public const string BUYER_CITY = 'buyer_city';

    public const string BUYER_COUNTRY_CODE = 'buyer_country_code';

    public const string EXTERNAL_ID = 'external_id';

    public const string EXTERNAL_TOKEN = 'external_token';

    public const string EXTERNAL_NUMBER = 'external_number';

    public const string EXTERNAL_URL = 'external_url';

    public const string SENT_AT = 'sent_at';

    public const string ERROR = 'error';

    public const string NOTES = 'notes';

    /** Kolumny migawki strony faktury bez prefiksu (`seller_<kolumna>`, `buyer_<kolumna>`). */
    public const array PARTY_COLUMNS = ['name', 'nip', 'street', 'postal_code', 'city', 'country_code'];

    protected $table = self::TABLE;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        ColumnNames::STATUS => InvoiceStatus::Draft->value,
        self::KIND => InvoiceKind::Vat->value,
        self::PAYMENT_METHOD => PaymentMethod::Transfer->value,
        self::TOTAL_NET => '0.00',
        self::TOTAL_VAT => '0.00',
        self::TOTAL_GROSS => '0.00',
    ];

    public function usesRecordStatus(): bool
    {
        return false;
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, Contractor::FOREIGN_KEY);
    }

    /**
     * Pozycje w kolejności `sort_order`.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        $items = $this->hasMany(InvoiceItem::class, self::FOREIGN_KEY);
        $items->orderBy(ColumnNames::SORT_ORDER);
        $items->orderBy(ColumnNames::ID);

        return $items;
    }

    /**
     * Numer do pokazania: nadany przez system fakturowy, a przed wysyłką numer roboczy z `name`.
     */
    public function number(): string
    {
        return $this->external_number ?? $this->name;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            Organization::FOREIGN_KEY => 'integer',
            Contractor::FOREIGN_KEY => 'integer',
            self::KIND => InvoiceKind::class,
            ColumnNames::STATUS => InvoiceStatus::class,
            self::ISSUE_DATE => 'immutable_date',
            self::SELL_DATE => 'immutable_date',
            self::PAYMENT_DUE => 'immutable_date',
            self::PAYMENT_METHOD => PaymentMethod::class,
            self::CURRENCY_CODE => 'string',
            self::TOTAL_NET => 'decimal:2',
            self::TOTAL_VAT => 'decimal:2',
            self::TOTAL_GROSS => 'decimal:2',
            self::SELLER_NAME => 'string',
            self::SELLER_NIP => 'string',
            self::SELLER_STREET => 'string',
            self::SELLER_POSTAL_CODE => 'string',
            self::SELLER_CITY => 'string',
            self::SELLER_COUNTRY_CODE => 'string',
            self::BUYER_NAME => 'string',
            self::BUYER_NIP => 'string',
            self::BUYER_STREET => 'string',
            self::BUYER_POSTAL_CODE => 'string',
            self::BUYER_CITY => 'string',
            self::BUYER_COUNTRY_CODE => 'string',
            self::EXTERNAL_ID => 'string',
            self::EXTERNAL_TOKEN => 'string',
            self::EXTERNAL_NUMBER => 'string',
            self::EXTERNAL_URL => 'string',
            self::SENT_AT => 'immutable_datetime',
            self::ERROR => 'string',
            self::NOTES => 'string',
        ];
    }
}
