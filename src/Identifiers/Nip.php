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
 * Polski numer identyfikacji podatkowej (NIP): normalizacja (same cyfry, bez prefiksu „PL”), suma kontrolna i postać
 * „XXX-XXX-XX-XX”. Poprawny NIP ma 10 cyfr, zaczyna się kodem urzędu skarbowego (bez 0 na początku i bez „x00”).
 */
final class Nip
{
    private const array WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    /**
     * Same cyfry („PL 525-123-45-67” → „5251234567”).
     */
    public static function normalise(?string $value): string
    {
        return (string) preg_replace('/\D/', '', (string) preg_replace('/^\s*PL/i', '', (string) $value));
    }

    public static function isValid(?string $value): bool
    {
        $nip = self::normalise($value);

        if (preg_match('/^[1-9]((\d[1-9])|([1-9]\d))\d{7}$/', $nip) !== 1) {
            return false;
        }

        return self::checkDigit($nip) === (int) $nip[9];
    }

    /**
     * Cyfra kontrolna dla pierwszych 9 cyfr albo null, gdy suma daje 10 (takiego NIP-u się nie nadaje).
     */
    public static function checkDigit(string $firstNineDigits): ?int
    {
        $sum = 0;

        foreach (self::WEIGHTS as $index => $weight) {
            $sum += ((int) $firstNineDigits[$index]) * $weight;
        }

        return $sum % 11 === 10 ? null : $sum % 11;
    }

    /**
     * „5251234567” → „525-123-45-67” (inne wartości bez zmian).
     */
    public static function format(string $nip): string
    {
        return preg_match('/^(\d{3})(\d{3})(\d{2})(\d{2})$/', $nip, $parts) === 1 ? "{$parts[1]}-{$parts[2]}-{$parts[3]}-{$parts[4]}" : $nip;
    }
}
