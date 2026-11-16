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

/**
 * Cykl tygodni grafiku albo umowy najmu: co $interval tygodni (1 — co tydzień), licząc od tygodnia ISO daty początku
 * ($start, zwykle `valid_from`). occursOn() — czy tydzień dnia należy do cyklu; coincides() — czy dwa cykle mają
 * wspólny tydzień (różnica tygodni początku podzielna przez NWD odstępów); within() — czy każdy tydzień tego cyklu
 * należy do $other (np. grafik najemcy w cyklu umowy).
 */
final readonly class WeekCycle
{
    /** Największy odstęp tygodni grafiku i umowy. */
    public const int MAX_INTERVAL = 8;

    private CarbonImmutable $anchor;

    public function __construct(
        public int $interval,
        CarbonImmutable $start,
    ) {
        $this->anchor = $start->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
    }

    public function occursOn(CarbonImmutable $day): bool
    {
        return $this->interval <= 1 || $this->weeksFrom($day) % $this->interval === 0;
    }

    public function coincides(self $other): bool
    {
        return $this->offset($other) % self::gcd(max(1, $this->interval), max(1, $other->interval)) === 0;
    }

    public function within(self $other): bool
    {
        if ($other->interval <= 1) {
            return true;
        }

        return $this->interval % $other->interval === 0 && $this->offset($other) % $other->interval === 0;
    }

    public function isEvery(): bool
    {
        return $this->interval <= 1;
    }

    /**
     * Tygodnie od początku cyklu do tygodnia dnia (ujemne przed początkiem — reszta liczona dodatnio).
     */
    private function weeksFrom(CarbonImmutable $day): int
    {
        $weeks = intdiv((int) round($this->anchor->diffInDays($day->startOfWeek(CarbonImmutable::MONDAY)->startOfDay(), false)), 7);

        return (($weeks % $this->interval) + $this->interval) % $this->interval;
    }

    /**
     * Bezwzględna różnica tygodni między początkami cykli.
     */
    private function offset(self $other): int
    {
        return abs(intdiv((int) round($this->anchor->diffInDays($other->anchor, false)), 7));
    }

    private static function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : self::gcd($b, $a % $b);
    }
}
