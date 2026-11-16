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

namespace Simplysmart\Polygon\Auth\Listeners;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Carbon;
use Simplysmart\Polygon\Auth\AccessRevocationReason;
use Simplysmart\Polygon\Auth\Events\AccountAccessRevoked;
use Simplysmart\Polygon\Auth\Events\TrustedDeviceRegistered;
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Http\AccountRoutes;
use Simplysmart\Polygon\Zones\ZoneRedirects;
use Simplysmart\Polygon\Mail\DeviceTokenReusedMail;
use Simplysmart\Polygon\Mail\NewTrustedDeviceMail;

/**
 * E-maile do właściciela konta o jego zaufanych urządzeniach: nowe urządzenie i użycie skopiowanego tokenu.
 */
final readonly class SendTrustedDeviceMails
{
    public function __construct(
        private Mailer $mailer,
        private UrlGenerator $url,
        private ZoneRegistry $zones,
    ) {}

    public function handle(TrustedDeviceRegistered|AccountAccessRevoked $event): void
    {
        $account = $event->account;
        $zone = $this->zones->get($event->zone);

        if ($event instanceof TrustedDeviceRegistered) {
            $devices = $zone->urlKey
                ? $this->url->route($zone->routeName(ZoneRedirects::LOGIN_ROUTE))
                : $this->url->route($zone->routeName(AccountRoutes::SECURITY)) . '#' . AccountRoutes::SECTION_DEVICES;
            $this->mailer->to($account->emailAddress())->queue(new NewTrustedDeviceMail($zone->name, $account->displayName(), $event->deviceName, Carbon::now(), $devices));
        } elseif ($event->reason === AccessRevocationReason::DeviceTokenReused) {
            $this->mailer->to($account->emailAddress())->queue(new DeviceTokenReusedMail($zone->name, $account->displayName()));
        }
    }
}
