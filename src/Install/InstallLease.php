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

namespace Simplysmart\Polygon\Install;

use Simplysmart\Polygon\Enums\LeaseAccess;
use Simplysmart\Polygon\Enums\LeaseMode;

/**
 * Umowa najmu zasobu z pliku instalacji (`organizations.<właściciel>.leases`) po walidacji: klucz zasobu właściciela,
 * klucze organizacji najemcy i jej kooperacji, numer umowy, okres (RRRR-MM-DD, bez końca: null), okna
 * [dzień ISO, od, do] i poziomy udostępnienia.
 */
final readonly class InstallLease
{
    /**
     * @param list<array{int, string, string}> $windows
     * @param list<LeaseAccess> $access
     */
    public function __construct(
        public string $resource,
        public string $lessee,
        public string $cooperation,
        public string $number,
        public ?string $validFrom,
        public ?string $validTo,
        public array $windows,
        public array $access,
        public int $weekInterval = 1,
        public LeaseMode $mode = LeaseMode::Windows,
    ) {}
}
