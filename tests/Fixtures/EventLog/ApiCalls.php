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

namespace Simplysmart\Polygon\Tests\Fixtures\EventLog;

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Contracts\EventLogSource;
use Simplysmart\Polygon\Enums\EventChannel;
use Simplysmart\Polygon\EventLog\LoggedEvent;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Źródło dziennika paczki w teście: jedno wywołanie API teraz.
 */
class ApiCalls implements EventLogSource
{
    public function recent(ZoneDefinition $zone, ZoneAccount $account, int $limit, ?EventChannel $channel = null): array
    {
        return $channel === null || $channel === EventChannel::Api ? [new LoggedEvent(EventChannel::Api, 'call', 'API', CarbonImmutable::now()->addMinute())] : [];
    }
}
