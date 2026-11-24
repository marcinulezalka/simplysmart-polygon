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

namespace Simplysmart\Polygon\Auth;

use Simplysmart\Polygon\Auth\SignIn\Steps\PinSetupStep;
use Simplysmart\Polygon\Auth\SignIn\Steps\PinStep;
use Simplysmart\Polygon\Contracts\SignInStep;

/**
 * Ustawienia logowania strefy: domyślne z `polygon.auth`, nadpisane kluczem `auth` configu strefy.
 */
final readonly class ZoneAuthSettings
{
    /**
     * @param list<class-string<SignInStep>> $steps
     * @param list<int> $lockoutMinutes Czas kolejnych blokad; ostatni powtarza się dla dalszych.
     * @param array<string, array{max: int, decay_minutes: int}> $limits
     * @param string $securityPath Segment adresu strony bezpieczeństwa pod `account/` (Http\AccountRoutes) — domyślnie
     *     `security`; strefa przechodząca na nowy widok tej strony nadpisuje go (np. `security-1`), żeby zwolnić
     *     dotychczasowy adres pod nową treść we własnych trasach, bez zmiany dla pozostałych stref.
     * @param array<string, string> $securityPages Sekcja bezpieczeństwa (Http\AccountRoutes::SECTIONS) => nazwa trasy
     *     strefy z jej własną stroną (Http\SecurityPage); sekcja bez wpisu jest na stronie bezpieczeństwa.
     */
    public function __construct(
        public array $steps,
        public int $codeLength,
        public int $codeTtlMinutes,
        public int $codeMaxAttempts,
        public int $codeResendCooldownSeconds,
        public PinLength $pinLength,
        public int $pinStageTtlMinutes,
        public int $pinMaxAttempts,
        public array $lockoutMinutes,
        public array $limits,
        public int $routeThrottlePerMinute,
        public int $challengePruneHours,
        public bool $trustedDevicesEnabled = false,
        public int $trustedDeviceLifetimeDays = 30,
        public int $trustedDevicesPerAccount = 10,
        public string $securityPath = 'security',
        public array $securityPages = [],
    ) {}

    /**
     * Czy konto bez PIN-u ustawia go dopiero po zalogowaniu (MissingPin): strefa ma krok PIN-u, ale nie krok jego ustawienia.
     */
    public function definesPinInAccount(): bool
    {
        return in_array(PinStep::class, $this->steps, true) && ! in_array(PinSetupStep::class, $this->steps, true);
    }

    /**
     * Czas blokady po `$lockLevel` wcześniejszych blokadach.
     */
    public function lockoutMinutesFor(int $lockLevel): int
    {
        return $this->lockoutMinutes[min(max($lockLevel, 0), count($this->lockoutMinutes) - 1)];
    }

    /**
     * @return array{max: int, decay_minutes: int}
     */
    public function limit(SignInLimit $limit): array
    {
        return $this->limits[$limit->value];
    }
}
