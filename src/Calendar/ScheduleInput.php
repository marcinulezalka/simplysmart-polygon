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

namespace Simplysmart\Polygon\Calendar;

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Grafik zasobu przed zapisem: jednostka, zasób, opcjonalny wykonawca (konto strefy), dzień tygodnia ISO, przedział
 * „HH:MM”, długość slotu w minutach (null — bez podziału na sloty), okres ważności (bez końca: null) i cykl tygodni
 * (co ile tygodni od tygodnia początku ważności; 1 — co tydzień).
 */
final readonly class ScheduleInput
{
    public function __construct(
        public int $unitId,
        public int $resourceId,
        public ?ZoneAccount $performer,
        public int $weekday,
        public string $startsAt,
        public string $endsAt,
        public ?int $slotMinutes,
        public CarbonImmutable $validFrom,
        public ?CarbonImmutable $validTo = null,
        public int $weekInterval = 1,
    ) {}

    /**
     * Czas „HH:MM:SS” jak w kolumnach `time`.
     */
    public static function time(string $time): string
    {
        return strlen($time) === 5 ? "{$time}:00" : $time;
    }

    /**
     * Cykl tygodni grafiku (co $weekInterval tygodni od tygodnia $validFrom).
     */
    public function cycle(): WeekCycle
    {
        return new WeekCycle($this->weekInterval, $this->validFrom);
    }

    public function startTime(): string
    {
        return self::time($this->startsAt);
    }

    public function endTime(): string
    {
        return self::time($this->endsAt);
    }

    /**
     * Długość przedziału w minutach.
     */
    public function minutes(): int
    {
        return (int) ((strtotime($this->endTime()) - strtotime($this->startTime())) / 60);
    }
}
