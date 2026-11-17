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

namespace Simplysmart\Polygon\Queries\EventLog;

use Illuminate\Contracts\Container\Container;
use Simplysmart\Polygon\Contracts\EventLogSource;
use Simplysmart\Polygon\Enums\EventChannel;
use Simplysmart\Polygon\EventLog\LoggedEvent;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Dziennik zdarzeń konta strefy (docs/baza-danych.md → „Dziennik zdarzeń konta”): najnowsze wpisy ze wszystkich źródeł
 * (Contracts\EventLogSource oznaczone tagiem SOURCES_TAG — polygon: zapisane zdarzenia i tabele historii), połączone
 * i posortowane od najnowszego. Działa dla każdej strefy bez konfiguracji.
 */
final readonly class AccountEventLog
{
    /** Tag kontenera źródeł dziennika (paczka dokłada własne: `$this->app->tag([Źródło::class], AccountEventLog::SOURCES_TAG)`). */
    public const string SOURCES_TAG = 'polygon.event_log.sources';

    /** Domyślna liczba wpisów. */
    public const int LIMIT = 20;

    public function __construct(
        private Container $container,
    ) {}

    /**
     * @return list<LoggedEvent>
     */
    public function recent(ZoneDefinition $zone, ZoneAccount $account, int $limit = self::LIMIT, ?EventChannel $channel = null): array
    {
        $events = [];

        foreach ($this->container->tagged(self::SOURCES_TAG) as $source) {
            if ($source instanceof EventLogSource) {
                array_push($events, ...$source->recent($zone, $account, $limit, $channel));
            }
        }

        usort($events, static fn(LoggedEvent $a, LoggedEvent $b): int => $b->createdAt <=> $a->createdAt);

        return array_slice($events, 0, $limit);
    }
}
