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

use InvalidArgumentException;
use Simplysmart\Polygon\Enums\InvoiceTax;
use Simplysmart\Polygon\Enums\TaxRateType;
use Simplysmart\Polygon\Models\Dictionaries\TaxRate;

/**
 * Stawka VAT w formacie pola `tax` pozycji faktury w API Fakturowni — jedno miejsce tłumaczenia: stawka z procentem →
 * liczba (`23`, `8`, `5`, `0`; ułamkowa jako float, np. `7.5`), zwolnienie → `zw`, „nie podlega” → `np`. Stawka słownika
 * polygon (Models\Dictionaries\TaxRate) tłumaczy się przez opodatkowanie pozycji: rodzaj Exempt to zwolnienie, każdy
 * inny — stawka z procentem (taxOf()).
 */
final class TaxRateCode
{
    public const string EXEMPT = 'zw';

    public const string NOT_APPLICABLE = 'np';

    /** Pole `tax` pozycji sprzedawcy niebędącego podatnikiem VAT. */
    public const string DISABLED = 'disabled';

    /**
     * @throws InvalidArgumentException Gdy stawka z procentem nie ma poprawnego procentu (0–100).
     */
    public static function of(InvoiceTax $tax, ?string $percent): int|float|string
    {
        if ($tax === InvoiceTax::Exempt) {
            return self::EXEMPT;
        }

        if ($tax === InvoiceTax::NotApplicable) {
            return self::NOT_APPLICABLE;
        }

        if ($percent === null || ! is_numeric($percent) || (float) $percent < 0 || (float) $percent > 100) {
            throw new InvalidArgumentException('Stawka VAT bez poprawnego procentu.');
        }

        $number = (float) $percent;

        return floor($number) === $number ? (int) $number : $number;
    }

    /**
     * Opodatkowanie pozycji z pola `tax` Fakturowni (odwrotność of()): liczba procentów → stawka, `zw` → zwolnienie,
     * `np` i `disabled` (sprzedawca bez VAT) → „nie podlega”; inna wartość (np. odwrotne obciążenie) — null.
     *
     * @return array{0: InvoiceTax, 1: string|null}|null
     */
    public static function parse(mixed $code): ?array
    {
        $code = is_string($code) || is_int($code) || is_float($code) ? strtolower(trim(str_replace(',', '.', (string) $code))) : '';

        return match (true) {
            $code === self::EXEMPT => [InvoiceTax::Exempt, null],
            $code === self::NOT_APPLICABLE, $code === self::DISABLED => [InvoiceTax::NotApplicable, null],
            preg_match('/^\d{1,3}(\.\d{1,2})?$/', $code) === 1 && (float) $code <= 100 => [InvoiceTax::Rate, number_format((float) $code, 2, '.', '')],
            default => null,
        };
    }

    /**
     * Opodatkowanie pozycji dla stawki słownika: [rodzaj, procent albo null].
     *
     * @return array{0: InvoiceTax, 1: string|null}
     */
    public static function taxOf(TaxRate $rate): array
    {
        return $rate->type === TaxRateType::Exempt ? [InvoiceTax::Exempt, null] : [InvoiceTax::Rate, $rate->rate];
    }
}
