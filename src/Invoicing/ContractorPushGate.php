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

namespace Simplysmart\Polygon\Invoicing;

/**
 * Bramka wysyłki kontrahentów do systemu fakturowego (singleton): zapis kontrahenta zleca wysyłkę tylko przy otwartej
 * bramce. Synchronizacja zamyka ją na czas własnych zapisów (closed()), żeby dane pobrane z Fakturowni nie były od
 * razu odsyłane z powrotem.
 */
final class ContractorPushGate
{
    private int $closed = 0;

    public function isOpen(): bool
    {
        return $this->closed === 0;
    }

    /**
     * @template TResult
     *
     * @param callable(): TResult $action
     *
     * @return TResult
     */
    public function closed(callable $action): mixed
    {
        $this->closed++;

        try {
            return $action();
        } finally {
            $this->closed--;
        }
    }
}
