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

use Simplysmart\Polygon\Enums\InvoiceTax;

/**
 * Kwoty pozycji faktury liczone na liczbach dziesiętnych (bcmath, bez float): netto = ilość × cena netto, VAT = netto ×
 * procent stawki (bez VAT-u dla „zw” i „np”), brutto = netto + VAT — każda zaokrąglona do groszy (połówki w górę).
 * decimal() czyta liczbę z formularza (kropka albo przecinek, do SCALE miejsc), sum() dodaje kwoty.
 */
final class InvoiceAmounts
{
    /** Dokładność obliczeń pośrednich. */
    private const int SCALE = 6;

    /**
     * @return array{net: numeric-string, vat: numeric-string, gross: numeric-string}
     */
    public static function of(string $quantity, string $unitPriceNet, InvoiceTax $tax, ?string $percent): array
    {
        $net = self::round(bcmul(self::number($quantity), self::number($unitPriceNet), self::SCALE));
        $vat = $tax->hasPercentage() ? self::round(bcdiv(bcmul($net, self::number($percent ?? '0'), self::SCALE), '100', self::SCALE)) : '0.00';

        return ['net' => $net, 'vat' => $vat, 'gross' => bcadd($net, $vat, 2)];
    }

    /**
     * @param iterable<string> $amounts
     *
     * @return numeric-string
     */
    public static function sum(iterable $amounts): string
    {
        $sum = '0.00';

        foreach ($amounts as $amount) {
            $sum = bcadd($sum, self::number($amount), 2);
        }

        return $sum;
    }

    /**
     * Liczba dziesiętna z tekstu formularza („1 234,50” → „1234.50”) albo null, gdy to nie liczba nieujemna.
     *
     * @return numeric-string|null
     */
    public static function decimal(?string $value): ?string
    {
        $value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $value));

        return preg_match('/^\d{1,9}(\.\d{1,' . self::SCALE . '})?$/', $value) === 1 && is_numeric($value) ? $value : null;
    }

    /**
     * @return numeric-string
     */
    private static function number(string $value): string
    {
        return self::decimal($value) ?? '0';
    }

    /**
     * Zaokrąglenie nieujemnej kwoty do groszy, połówki w górę.
     *
     * @param numeric-string $value
     *
     * @return numeric-string
     */
    private static function round(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }
}
