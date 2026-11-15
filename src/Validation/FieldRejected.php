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

namespace Simplysmart\Polygon\Validation;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Zapis odrzucony przez regułę domeny: błędne pole formularza ($field) z komunikatem do pokazania przy nim.
 */
abstract class FieldRejected extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages([$this->field => $this->getMessage()]);
    }
}
