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

namespace Simplysmart\Polygon\EventLog\Listeners;

use Simplysmart\Polygon\Auth\Events\AccountAccessRevoked;
use Simplysmart\Polygon\Auth\Events\AccountLocked;
use Simplysmart\Polygon\Auth\Events\PinChanged;
use Simplysmart\Polygon\Auth\Events\PinReset;
use Simplysmart\Polygon\Auth\Events\RecoveryEmailChanged;
use Simplysmart\Polygon\Auth\Events\SignedOut;
use Simplysmart\Polygon\Auth\Events\SignInRejected;
use Simplysmart\Polygon\Auth\Events\SignInSucceeded;
use Simplysmart\Polygon\Auth\Events\TrustedDeviceRegistered;
use Simplysmart\Polygon\Auth\Events\TrustedDevicesRevoked;
use Simplysmart\Polygon\Enums\EventChannel;
use Simplysmart\Polygon\EventLog\EventLog;

/**
 * Zdarzenia logowania i konta (Auth\Events) w dzienniku zdarzeń jako kanał bezpieczeństwa: typ zdarzenia (EVENTS),
 * adres IP, gdy zdarzenie go zna, i wartości opisu (`event_log.types.<typ>`).
 */
final readonly class RecordSecurityEvent
{
    /** Zdarzenia zapisywane przez ten listener. */
    public const array EVENTS = [
        SignInSucceeded::class,
        SignInRejected::class,
        SignedOut::class,
        AccountLocked::class,
        AccountAccessRevoked::class,
        PinChanged::class,
        PinReset::class,
        RecoveryEmailChanged::class,
        TrustedDeviceRegistered::class,
        TrustedDevicesRevoked::class,
    ];

    public function __construct(
        private EventLog $log,
    ) {}

    public function handle(object $event): void
    {
        [$zone, $accountId, $type, $context, $ip] = match (true) {
            $event instanceof SignInSucceeded => [$event->zone, $event->account->getAuthIdentifier(), 'sign_in', [], $event->ip],
            $event instanceof SignInRejected => [$event->zone, $event->account->getAuthIdentifier(), 'sign_in_rejected', ['attempts' => $event->failedAttempts], $event->ip],
            $event instanceof SignedOut => [$event->zone, $event->accountId, 'signed_out', [], null],
            $event instanceof AccountLocked => [$event->zone, $event->account->getAuthIdentifier(), 'account_locked', ['until' => local_date($event->until) . ' ' . $event->until->format('H:i')], null],
            $event instanceof AccountAccessRevoked => [$event->zone, $event->account->getAuthIdentifier(), 'access_revoked', ['reason' => $event->reason->value], null],
            $event instanceof PinChanged => [$event->zone, $event->account->getAuthIdentifier(), 'pin_changed', [], $event->ip],
            $event instanceof PinReset => [$event->zone, $event->account->getAuthIdentifier(), 'pin_reset', [], $event->ip],
            $event instanceof RecoveryEmailChanged => [$event->zone, $event->account->getAuthIdentifier(), $event->set ? 'recovery_email_set' : 'recovery_email_removed', [], $event->ip],
            $event instanceof TrustedDeviceRegistered => [$event->zone, $event->account->getAuthIdentifier(), 'trusted_device_registered', ['device' => $event->deviceName], $event->ip],
            $event instanceof TrustedDevicesRevoked => [$event->zone, $event->accountId, 'trusted_devices_revoked', ['count' => $event->count], null],
            default => [null, null, '', [], null],
        };

        if (is_string($zone) && (is_int($accountId) || is_string($accountId))) {
            $this->log->record($zone, $accountId, EventChannel::Security, $type, context: $context, ip: $ip);
        }
    }
}
