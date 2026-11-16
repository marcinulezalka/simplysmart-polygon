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

namespace Simplysmart\Polygon\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simplysmart\Polygon\Identifiers\Pesel as PeselNumber;

/**
 * Poprawny PESEL (11 cyfr, suma kontrolna i prawdziwa data urodzenia). Komunikat: `identifiers.pesel_invalid`.
 */
final class Pesel implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PeselNumber::tryFrom($value) === null) {
            $fail(text('identifiers.pesel_invalid'));
        }
    }
}
