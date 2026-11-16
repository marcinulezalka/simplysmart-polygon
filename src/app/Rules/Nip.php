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
use Simplysmart\Polygon\Identifiers\Nip as NipNumber;

/**
 * Poprawny NIP (10 cyfr z sumą kontrolną; dopuszczalne myślniki, spacje i prefiks „PL”). Komunikat: `identifiers.nip_invalid`.
 */
final class Nip implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! NipNumber::isValid($value)) {
            $fail(text('identifiers.nip_invalid'));
        }
    }
}
