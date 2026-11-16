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

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Simplysmart\Polygon\Enums\InvoiceTax;
use Simplysmart\Polygon\Models\Dictionaries\TaxRate;

/**
 * Pozycja faktury (`invoice_items`, docs/baza-danych.md §21): `name` — nazwa towaru albo usługi, `quantity` (do trzech
 * miejsc po przecinku), `unit` (np. „szt.”, „usł.”), `unit_price_net`, opodatkowanie — `tax` (Enums\InvoiceTax) z migawką
 * procentu `tax_percent` z dnia sprzedaży (bez procentu dla „zw” i „np”) i opcjonalnym `tax_rate_id` (stawka słownika
 * `locale_tax_rates`, z której pozycja powstała) — oraz kwoty `net`, `vat`, `gross` policzone przy wystawieniu
 * (Invoicing\InvoiceAmounts). Kolejność pozycji: `sort_order`.
 *
 * @property int $invoice_id
 * @property string $quantity
 * @property string|null $unit
 * @property string $unit_price_net
 * @property int|null $tax_rate_id
 * @property InvoiceTax $tax
 * @property string|null $tax_percent
 * @property string $net
 * @property string $vat
 * @property string $gross
 * @property-read Invoice|null $invoice
 * @property-read TaxRate|null $taxRate
 */
final class InvoiceItem extends DataModel
{
    public const string TABLE = 'invoice_items';

    public const string QUANTITY = 'quantity';

    public const string UNIT = 'unit';

    public const string UNIT_PRICE_NET = 'unit_price_net';

    public const string TAX_RATE_ID = 'tax_rate_id';

    public const string TAX = 'tax';

    public const string TAX_PERCENT = 'tax_percent';

    public const string NET = 'net';

    public const string VAT = 'vat';

    public const string GROSS = 'gross';

    protected $table = self::TABLE;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true, self::TAX => InvoiceTax::Rate->value];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, Invoice::FOREIGN_KEY);
    }

    /**
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, self::TAX_RATE_ID);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            Invoice::FOREIGN_KEY => 'integer',
            self::QUANTITY => 'decimal:3',
            self::UNIT => 'string',
            self::UNIT_PRICE_NET => 'decimal:2',
            self::TAX_RATE_ID => 'integer',
            self::TAX => InvoiceTax::class,
            self::TAX_PERCENT => 'decimal:2',
            self::NET => 'decimal:2',
            self::VAT => 'decimal:2',
            self::GROSS => 'decimal:2',
        ];
    }
}
