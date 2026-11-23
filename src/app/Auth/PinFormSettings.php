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

use Simplysmart\Polygon\Contracts\CurrentZone;

/**
 * Długości do formularzy PIN-u bieżącej strefy (strona bezpieczeństwa — Http\Controllers\Account\SecurityController —
 * i ekrany PIN-u stref): długość nowego PIN-u ze ZoneAuthSettings, długość, którą przyjmuje pole obecnego PIN-u
 * (PinLength::accepted() — także PIN-y ustawione przy dawnych granicach) i długość kodu z e-maila.
 */
final readonly class PinFormSettings
{
    public function __construct(
        private CurrentZone $zone,
    ) {}

    /**
     * @return array{pinLength: PinLength, currentPinLength: PinLength, codeLength: int}
     */
    public function toArray(): array
    {
        $settings = $this->zone->get()->auth();

        return [
            'pinLength' => $settings->pinLength,
            'currentPinLength' => PinLength::accepted(),
            'codeLength' => $settings->codeLength,
        ];
    }
}
