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

namespace Simplysmart\Polygon\Identifiers;

use Carbon\CarbonImmutable;

/**
 * Polski numer PESEL: suma kontrolna oraz zakodowana data urodzenia i płeć.
 */
final readonly class Pesel
{
    private const array WEIGHTS = [1, 3, 7, 9, 1, 3, 7, 9, 1, 3];

    /**
     * Przesunięcie miesiąca => stulecie (+80 → XIX w., +0 → XX w., +20 → XXI w., …).
     *
     * @var array<int, int>
     */
    private const array CENTURIES = [80 => 1800, 0 => 1900, 20 => 2000, 40 => 2100, 60 => 2200];

    private function __construct(
        public string $number,
        public CarbonImmutable $birthDate,
    ) {}

    /**
     * PESEL albo null, gdy ma zły format, złą sumę kontrolną albo niemożliwą datę.
     */
    public static function tryFrom(?string $value): ?self
    {
        $number = trim((string) $value);

        if (preg_match('/^\d{11}$/', $number) !== 1 || self::checkDigit($number) !== (int) $number[10]) {
            return null;
        }

        $birthDate = self::birthDateOf($number);

        return $birthDate === null ? null : new self($number, $birthDate);
    }

    /**
     * „M” — mężczyzna, „F” — kobieta (parzystość 10. cyfry).
     */
    public function sex(): string
    {
        return ((int) $this->number[9]) % 2 === 1 ? 'M' : 'F';
    }

    /**
     * Cyfra kontrolna (11.) dla pierwszych 10 cyfr.
     */
    public static function checkDigit(string $firstTenDigits): int
    {
        $sum = 0;

        foreach (self::WEIGHTS as $index => $weight) {
            $sum += ((int) $firstTenDigits[$index]) * $weight;
        }

        return (10 - $sum % 10) % 10;
    }

    private static function birthDateOf(string $number): ?CarbonImmutable
    {
        $year = (int) substr($number, 0, 2);
        $month = (int) substr($number, 2, 2);
        $day = (int) substr($number, 4, 2);

        foreach (self::CENTURIES as $offset => $century) {
            if ($month > $offset && $month <= $offset + 12) {
                $year += $century;
                $month -= $offset;

                return checkdate($month, $day, $year) ? CarbonImmutable::create($year, $month, $day) : null;
            }
        }

        return null;
    }
}
