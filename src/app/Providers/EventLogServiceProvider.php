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

namespace Simplysmart\Polygon\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Simplysmart\Polygon\EventLog\Listeners\RecordSecurityEvent;
use Simplysmart\Polygon\EventLog\Sources\HistoryEvents;
use Simplysmart\Polygon\EventLog\Sources\StoredEvents;
use Simplysmart\Polygon\Queries\EventLog\AccountEventLog;

/**
 * Dziennik zdarzeń konta (docs/baza-danych.md → „Dziennik zdarzeń konta”): źródła wpisów polygonu (zapisane zdarzenia
 * i tabele historii) pod tagiem AccountEventLog::SOURCES_TAG oraz zapis zdarzeń bezpieczeństwa logowania; powiadomienia
 * zapisuje middleware grupy strefy (Http\Middleware\RecordNotificationEvent).
 */
final class EventLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([StoredEvents::class, HistoryEvents::class], AccountEventLog::SOURCES_TAG);
    }

    public function boot(Dispatcher $events): void
    {
        foreach (RecordSecurityEvent::EVENTS as $event) {
            $events->listen($event, RecordSecurityEvent::class);
        }
    }
}
