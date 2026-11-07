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
 * Dane kontrahenta z formularza (przed normalizacją NIP-u i REGON-u w SaveContractor).
 */
final readonly class ContractorData
{
    public function __construct(
        public string $name,
        public ?string $shortName = null,
        public ?string $nip = null,
        public ?string $regon = null,
        public ?string $krs = null,
        public ?string $street = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $countryCode = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $notes = null,
        public bool $active = true,
    ) {}
}
