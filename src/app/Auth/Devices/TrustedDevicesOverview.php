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

namespace Simplysmart\Polygon\Auth\Devices;

use Illuminate\Http\Request;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Contracts\TrustedDeviceRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Zaufane urządzenia zalogowanego konta do widoku (strona bezpieczeństwa — Http\Controllers\Account\SecurityController
 * — i jej odpowiedniki w strefach): aktywne urządzenia i selektor urządzenia bieżącej przeglądarki (ciasteczko
 * DeviceCookie — wyróżnienie „to urządzenie”). Strefa bez zaufanych urządzeń (`trustedDevicesEnabled`) — oba null,
 * widok wtedy pomija sekcję.
 */
final readonly class TrustedDevicesOverview
{
    public function __construct(
        private CurrentZone $zone,
        private CurrentAccount $account,
        private TrustedDeviceRepository $devices,
        private DeviceCookie $cookie,
    ) {}

    /**
     * @return array{devices: list<TrustedDeviceRecord>|null, currentSelector: string|null}
     */
    public function forRequest(Request $request): array
    {
        $zone = $this->zone->get();
        $accountId = $this->account->id() ?? throw new AccessDeniedHttpException();

        if (! $zone->auth()->trustedDevicesEnabled) {
            return ['devices' => null, 'currentSelector' => null];
        }

        return [
            'devices' => $this->devices->activeFor($accountId),
            'currentSelector' => $this->cookie->read($zone, $request)?->selector,
        ];
    }
}
