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

namespace Simplysmart\Polygon\Localization;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Formaty daty i godziny ekranów pracy (adresy, formularze, klucze dni) i ścisłe parsowanie daty RRRR-MM-DD.
 */
final class DateFormat
{
    /** Data w adresach, formularzach i kluczach dni. */
    public const string DATE = 'Y-m-d';

    /** Data w formacie lokalnym „DD-MM-RRRR” (pola daty urodzenia, dane pacjenta na ekranach). */
    public const string LOCAL = 'd-m-Y';

    /** Godzina „GG:MM”. */
    public const string TIME = 'H:i';

    /** Termin w formularzach zapisu. */
    public const string DATE_TIME = self::DATE . ' ' . self::TIME;

    /**
     * Data RRRR-MM-DD (np. z `?date=`, `?week=` albo pola formularza); błędna (także w innym formacie) albo pusta — null.
     */
    public static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $day = CarbonImmutable::createFromFormat('!' . self::DATE, $value);
        } catch (InvalidFormatException) {
            return null;
        }

        return $day instanceof CarbonImmutable && $day->format(self::DATE) === $value ? $day : null;
    }

    /**
     * Data z pola formularza: lokalna DD-MM-RRRR (pole daty strefy) albo RRRR-MM-DD (np. natywne pole daty);
     * błędna albo pusta — null.
     */
    public static function parseInput(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', trim($value), $local) !== 1) {
            return self::parse(is_string($value) ? trim($value) : $value);
        }

        return self::parse("{$local[3]}-{$local[2]}-{$local[1]}");
    }
}
