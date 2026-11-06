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

namespace Simplysmart\Polygon\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Simplysmart\Polygon\Rules\StrongPin;

/**
 * Jedyne źródło reguł walidacji PIN-u (logowanie, ustawienie i zmiana PIN-u).
 */
final class PinRules
{
    /**
     * Wpisany istniejący PIN: same cyfry w pełnym zakresie polygon, więc zmiana zakresu strefy nikogo nie blokuje.
     *
     * @return list<string>
     */
    public static function forEnteredPin(): array
    {
        return self::digits(PinLength::accepted());
    }

    /**
     * Nowy PIN: zakres strefy, potwierdzenie (`<pole>_confirmation`) i odrzucenie łatwych PIN-ów.
     *
     * @return list<string|ValidationRule>
     */
    public static function forNewPin(PinLength $length): array
    {
        return [...self::digits($length), 'confirmed', new StrongPin()];
    }

    /**
     * @return list<string>
     */
    private static function digits(PinLength $length): array
    {
        return ['required', 'string', "digits_between:{$length->min},{$length->max}"];
    }
}
