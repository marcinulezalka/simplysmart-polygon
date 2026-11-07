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

namespace Simplysmart\Polygon\Leases;

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Calendar\WeekCycle;
use Simplysmart\Polygon\Enums\LeaseAccess;
use Simplysmart\Polygon\Models\Organization;

/**
 * Umowa najmu przed zapisem (SaveLease): organizacja właściciela (kontekst pracy — zasób musi należeć do niej albo jej
 * przodka), zasób, organizacja najemcy z jej podorganizacją kooperacji, numer umowy, opcjonalna umowa kontrahenta
 * właściciela, okres (bez końca: null), okna godzinowe, poziomy udostępnienia i cykl tygodni okien (1 — co tydzień).
 */
final readonly class LeaseInput
{
    /**
     * @param list<LeaseAccess> $access
     */
    public function __construct(
        public Organization $owner,
        public int $resourceId,
        public Organization $lessee,
        public Organization $cooperation,
        public string $number,
        public CarbonImmutable $validFrom,
        public ?CarbonImmutable $validTo = null,
        public LeaseWindows $windows = new LeaseWindows(),
        public array $access = [],
        public ?int $contractId = null,
        public int $weekInterval = 1,
    ) {}

    /**
     * Cykl tygodni okien (co $weekInterval tygodni od tygodnia $validFrom).
     */
    public function cycle(): WeekCycle
    {
        return new WeekCycle($this->weekInterval, $this->validFrom);
    }

    /**
     * Poziomy udostępnienia do zapisu (bez powtórzeń).
     *
     * @return list<string>
     */
    public function accessValues(): array
    {
        return array_values(array_unique(array_map(static fn(LeaseAccess $level): string => $level->value, $this->access)));
    }
}
