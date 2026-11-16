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

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zapis odrzucony przez regułę domeny: błędne pole formularza ($field) z komunikatem do pokazania przy nim. Nieprzechwycony
 * w żądaniu HTTP renderuje się jak błąd walidacji (render()) i nie jest logowany.
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

    /**
     * Odpowiedź HTTP jak dla błędu walidacji (handler wyjątków Laravela: powrót z błędem pod polem albo JSON 422), więc
     * kontrolery nie muszą przechwytywać odrzucenia, żeby je przepakować.
     */
    public function render(Request $request): Response
    {
        return app(ExceptionHandler::class)->render($request, $this->toValidationException());
    }

    /**
     * Odrzucenie to błąd danych użytkownika, nie aplikacji — handler go nie loguje.
     */
    public function report(): bool
    {
        return true;
    }
}
