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

namespace Simplysmart\Polygon\Http;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Simplysmart\Polygon\Localization\DateFormat;

/**
 * Odczyt pól żądania ekranów stref: tekst wymagany i opcjonalny, dzień z `?date=` i data z pola formularza wpisywana
 * lokalnie (DateFormat::parseInput()).
 */
final class RequestInput
{
    /** Parametr adresu z dniem ekranu (RRRR-MM-DD). */
    public const string DATE = 'date';

    /**
     * Tekst pola wymaganego (po walidacji `required`), przycięty.
     */
    public static function required(Request $request, string $field): string
    {
        return $request->string($field)->trim()->value();
    }

    /**
     * Opcjonalny tekst z żądania (pusty — null).
     */
    public static function text(Request $request, string $field): ?string
    {
        $value = self::required($request, $field);

        return $value === '' ? null : $value;
    }

    /**
     * Dzień ekranu z `?date=` (DateFormat::parse()); bez niego albo błędny — dziś.
     */
    public static function day(Request $request): CarbonImmutable
    {
        return DateFormat::parse($request->query(self::DATE)) ?? CarbonImmutable::today();
    }

    /**
     * Data z pola formularza (DD-MM-RRRR albo RRRR-MM-DD — DateFormat::parseInput()); puste
     * pole — null.
     *
     * @throws ValidationException Gdy pole jest wymagane i puste albo data jest nieczytelna.
     */
    public static function date(Request $request, string $field, bool $required = false): ?CarbonImmutable
    {
        $value = self::text($request, $field);

        if ($value === null && ! $required) {
            return null;
        }

        return DateFormat::parseInput($value) ?? throw ValidationException::withMessages([$field => text('polygon::forms.date_invalid')]);
    }
}
