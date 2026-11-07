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

use RuntimeException;

/**
 * Grafik nie został zapisany ani usunięty: kolizje (ScheduleConflict), powody paczki strefy (teksty) albo przyszłe
 * rezerwacje w usuwanym grafiku. reasons() — komunikaty do pokazania przy formularzu.
 */
final class ScheduleRejected extends RuntimeException
{
    /**
     * @param list<ScheduleConflict> $conflicts
     * @param list<string> $extra Powody spoza ScheduleConflict (np. z paczki strefy).
     */
    public function __construct(
        public readonly array $conflicts,
        public readonly array $extra = [],
    ) {
        parent::__construct(implode(' ', $this->reasons()));
    }

    /**
     * @param list<string> $reasons
     */
    public static function because(array $reasons): self
    {
        return new self([], $reasons);
    }

    /**
     * @return list<string>
     */
    public function reasons(): array
    {
        return [...array_map(static fn(ScheduleConflict $conflict): string => $conflict->label(), $this->conflicts), ...$this->extra];
    }
}
