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

namespace Simplysmart\Polygon\Contracts;

use Simplysmart\Polygon\Enums\EventChannel;
use Simplysmart\Polygon\EventLog\LoggedEvent;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Źródło wpisów dziennika zdarzeń konta (docs/baza-danych.md → „Dziennik zdarzeń konta”). Polygon ma dwa: zapisane zdarzenia
 * (EventLog\Sources\StoredEvents — powiadomienia, bezpieczeństwo, API) i tabele historii (EventLog\Sources\HistoryEvents);
 * paczka dokłada własne, oznaczając klasę tagiem Queries\EventLog\AccountEventLog::SOURCES_TAG.
 */
interface EventLogSource
{
    /**
     * Najnowsze wpisy konta strefy (od najnowszego, najwyżej $limit); z $channel — tylko z tego kanału.
     *
     * @return list<LoggedEvent>
     */
    public function recent(ZoneDefinition $zone, ZoneAccount $account, int $limit, ?EventChannel $channel = null): array;
}
