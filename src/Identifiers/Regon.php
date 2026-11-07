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

/**
 * Polski numer REGON: 9 cyfr albo 14 dla jednostki lokalnej (pierwsze 9 cyfr to poprawny REGON), każdy z cyfrą
 * kontrolną.
 */
final class Regon
{
    private const array WEIGHTS_9 = [8, 9, 2, 3, 4, 5, 6, 7];

    private const array WEIGHTS_14 = [2, 4, 8, 5, 0, 9, 7, 3, 6, 1, 2, 4, 8];

    /**
     * Same cyfry („123-456-785” → „123456785”) albo null dla pustej wartości.
     */
    public static function normalise(?string $value): ?string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    public static function isValid(?string $value): bool
    {
        $regon = (string) $value;

        return match (true) {
            preg_match('/^\d{9}$/', $regon) === 1 => self::checkDigit($regon, self::WEIGHTS_9) === (int) $regon[8],
            preg_match('/^\d{14}$/', $regon) === 1 => self::isValid(substr($regon, 0, 9)) && self::checkDigit($regon, self::WEIGHTS_14) === (int) $regon[13],
            default => false,
        };
    }

    /**
     * Cyfra kontrolna 9-cyfrowego REGON-u dla pierwszych 8 cyfr.
     */
    public static function checkDigit9(string $firstEightDigits): int
    {
        return self::checkDigit($firstEightDigits, self::WEIGHTS_9);
    }

    /**
     * @param list<int> $weights
     */
    private static function checkDigit(string $digits, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += ((int) $digits[$index]) * $weight;
        }

        return $sum % 11 % 10;
    }
}
