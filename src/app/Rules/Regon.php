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
use Simplysmart\Polygon\Identifiers\Regon as RegonNumber;

/**
 * Poprawny REGON (9 albo 14 cyfr z cyframi kontrolnymi; dopuszczalne myślniki i spacje). Komunikat: `identifiers.regon_invalid`.
 */
final class Regon implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! RegonNumber::isValid(RegonNumber::normalise($value))) {
            $fail(text('identifiers.regon_invalid'));
        }
    }
}
