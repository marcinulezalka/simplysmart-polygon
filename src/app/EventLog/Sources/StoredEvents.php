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

namespace Simplysmart\Polygon\EventLog\Sources;

use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Simplysmart\Polygon\Contracts\EventLogSource;
use Simplysmart\Polygon\Enums\EventChannel;
use Simplysmart\Polygon\EventLog\EventLog;
use Simplysmart\Polygon\EventLog\LoggedEvent;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Zones\ZoneDefinition;

/**
 * Wpisy dziennika z tabeli `account_events` (EventLog\EventLog): powiadomienia z gotową treścią, a zdarzenia bez treści
 * z opisem typu `event_log.types.<typ>` (teksty strefy, potem polygon) z wartościami z `json_context`.
 */
final readonly class StoredEvents implements EventLogSource
{
    public function __construct(
        private DatabaseManager $connections,
    ) {}

    public function recent(ZoneDefinition $zone, ZoneAccount $account, int $limit, ?EventChannel $channel = null): array
    {
        if ($channel === EventChannel::History) {
            return [];
        }

        $query = $this->connections->connection()->table(EventLog::TABLE);
        $query->where(EventLog::ZONE, $zone->name);
        $query->where(EventLog::ACCOUNT_ID, $account->getKey());

        if ($channel !== null) {
            $query->where(EventLog::CHANNEL, $channel->value);
        }

        $query->orderByDesc(EventLog::CREATED_AT)->orderByDesc('id')->limit($limit);
        $events = [];

        foreach ($query->get() as $row) {
            $channelValue = EventChannel::tryFrom(is_string($row->{EventLog::CHANNEL}) ? $row->{EventLog::CHANNEL} : '');
            $type = is_string($row->{EventLog::TYPE}) ? $row->{EventLog::TYPE} : '';
            $message = $row->{EventLog::MESSAGE};
            $ip = $row->{EventLog::IP};

            if ($channelValue === null) {
                continue;
            }

            $events[] = new LoggedEvent(
                $channelValue,
                $type,
                is_string($message) && $message !== '' ? $message : text($zone->textKey('event_log.types.' . $type), self::context($row->{EventLog::CONTEXT})),
                CarbonImmutable::parse(is_string($row->{EventLog::CREATED_AT}) ? $row->{EventLog::CREATED_AT} : 'now'),
                is_string($ip) ? $ip : null,
            );
        }

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    private static function context(mixed $json): array
    {
        $context = is_string($json) ? json_decode($json, true) : null;

        return is_array($context) ? array_filter($context, is_string(...), ARRAY_FILTER_USE_KEY) : [];
    }
}
