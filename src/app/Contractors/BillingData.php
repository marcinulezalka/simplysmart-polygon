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

namespace Simplysmart\Polygon\Contractors;

/**
 * Dane do faktury kontrahenta z formularza (przed normalizacją NIP-u w SaveBillingData): pełna nazwa nabywcy, NIP,
 * adres, kraj, osoba kontaktowa i adres e-mail do wysyłki faktur.
 */
final readonly class BillingData
{
    public function __construct(
        public ?string $name = null,
        public ?string $nip = null,
        public ?string $street = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $countryCode = null,
        public ?string $contactName = null,
        public ?string $email = null,
    ) {}
}
