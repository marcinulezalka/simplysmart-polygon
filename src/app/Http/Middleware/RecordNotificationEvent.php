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

namespace Simplysmart\Polygon\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Simplysmart\Polygon\Contracts\CurrentAccount;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Enums\EventChannel;
use Simplysmart\Polygon\EventLog\EventLog;
use Simplysmart\Polygon\Http\StatusMessage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Powiadomienia w dzienniku zdarzeń konta: komunikat statusu (Http\StatusMessage) wstawiony w tym żądaniu do sesji
 * strefy — ten sam, który pokazuje toast i dzwonek paska (Notifications\FlashNotifications) — trafia do dziennika jako
 * kanał powiadomień, typ `status`, dla konta zalogowanego po odpowiedzi. Każda strefa z kontami ma to w grupie
 * middleware (Zones\ZoneMiddlewareRegistrar), więc żadna paczka nie zapisuje powiadomień sama.
 */
final readonly class RecordNotificationEvent
{
    public const string TYPE = 'status';

    public function __construct(
        private CurrentAccount $account,
        private CurrentZone $zone,
        private EventLog $log,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->hasSession() || ! $this->zone->has()) {
            return $response;
        }

        $session = $request->session();
        $fresh = $session->get('_flash.new', []);
        $message = $session->get(StatusMessage::KEY);
        $accountId = $this->account->id();

        if (is_array($fresh) && in_array(StatusMessage::KEY, $fresh, true) && is_string($message) && $message !== '' && $accountId !== null) {
            $this->log->record($this->zone->get()->name, $accountId, EventChannel::Notification, self::TYPE, $message, ip: $request->ip());
        }

        return $response;
    }
}
