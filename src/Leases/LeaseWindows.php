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

/**
 * Okna godzinowe umowy najmu zasobu (kolumna `resource_leases.windows`): lista [dzień tygodnia ISO, od, do] („HH:MM”).
 * W jednym dniu może być kilka okien (np. 08:00–12:00 i 18:00–22:00). Bez okien najemca ma zasób przez całą dobę
 * w każdym dniu — wtedy każdy przedział jest pokryty i każdy się nakłada.
 */
final readonly class LeaseWindows
{
    /** Czas „HH:MM”. */
    private const string TIME = '/^([01]\\d|2[0-3]):[0-5]\\d$/';

    /**
     * @param list<array{int, string, string}> $windows
     */
    public function __construct(
        public array $windows = [],
    ) {}

    /**
     * Okna z zapisanej wartości (pomija niepoprawne wpisy).
     */
    public static function fromStored(mixed $stored): self
    {
        $windows = [];

        foreach (is_array($stored) ? $stored : [] as $window) {
            if (is_array($window) && is_int($window[0] ?? null) && is_string($window[1] ?? null) && is_string($window[2] ?? null)) {
                $windows[] = [$window[0], substr($window[1], 0, 5), substr($window[2], 0, 5)];
            }
        }

        return new self($windows);
    }

    public function isWhole(): bool
    {
        return $this->windows === [];
    }

    /**
     * Czy przedział dnia ($from–$to, „HH:MM”) mieści się w jednym oknie.
     */
    public function covers(int $weekday, string $from, string $to): bool
    {
        return $this->any($weekday, static fn(string $start, string $end): bool => $start <= $from && $to <= $end);
    }

    /**
     * Czy przedział dnia ($from–$to, „HH:MM”) nakłada się na któreś okno.
     */
    public function overlaps(int $weekday, string $from, string $to): bool
    {
        return $this->any($weekday, static fn(string $start, string $end): bool => $start < $to && $from < $end);
    }

    /**
     * Czy któreś okno nakłada się na okno innej umowy.
     */
    public function overlapsWindows(self $other): bool
    {
        if ($this->isWhole() || $other->isWhole()) {
            return true;
        }

        foreach ($this->windows as [$weekday, $from, $to]) {
            if ($other->overlaps($weekday, $from, $to)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Czy okna są poprawne: dzień 1–7, „HH:MM”, początek przed końcem i bez nakładania się w jednym dniu.
     */
    public function isValid(): bool
    {
        foreach ($this->windows as $index => [$weekday, $from, $to]) {
            if ($weekday < 1 || $weekday > 7 || preg_match(self::TIME, $from) !== 1 || preg_match(self::TIME, $to) !== 1 || $from >= $to) {
                return false;
            }

            foreach (array_slice($this->windows, $index + 1) as [$otherDay, $otherFrom, $otherTo]) {
                if ($otherDay === $weekday && $otherFrom < $to && $from < $otherTo) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Okna do zapisu, po dniu i godzinie.
     *
     * @return list<array{int, string, string}>
     */
    public function toArray(): array
    {
        $windows = $this->windows;
        usort($windows, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return $windows;
    }

    /**
     * Okna w jednej linii („wt 08:00–12:00, wt 18:00–22:00”), a bez okien null (cały zasób).
     */
    public function label(): ?string
    {
        if ($this->isWhole()) {
            return null;
        }

        return implode(', ', array_map(static fn(array $window): string => local_day($window[0], 'dd') . ' ' . $window[1] . '–' . $window[2], $this->toArray()));
    }

    /**
     * @param callable(string, string): bool $matches Okno (od, do) pasuje do przedziału.
     */
    private function any(int $weekday, callable $matches): bool
    {
        if ($this->isWhole()) {
            return true;
        }

        foreach ($this->windows as [$day, $from, $to]) {
            if ($day === $weekday && $matches($from, $to)) {
                return true;
            }
        }

        return false;
    }
}
