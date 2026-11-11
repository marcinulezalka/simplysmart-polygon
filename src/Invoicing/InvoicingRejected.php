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

use RuntimeException;

/**
 * Konto systemu fakturowego albo faktura nie zostały zapisane: błędne pole ($field) z komunikatem do pokazania przy
 * formularzu (wzór: Contractors\ContractorRejected).
 */
final class InvoicingRejected extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
