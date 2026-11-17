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

namespace Simplysmart\Polygon\EventLog;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use JsonException;
use Simplysmart\Polygon\Enums\EventChannel;

/**
 * Zapis zdarzeń konta do tabeli technicznej `account_events` (docs/baza-danych.md → „Dziennik zdarzeń konta”) — kanały inne niż historia,
 * którą zapisują triggery tabel danych: powiadomienia (Http\Middleware\RecordNotificationEvent), bezpieczeństwo
 * (EventLog\Listeners\RecordSecurityEvent), w przyszłości API. Tabela bez historii i bez standardowych kolumn
 * (dziennik tylko dopisuje).
 */
final readonly class EventLog
{
    public const string TABLE = 'account_events';

    public const string ZONE = 'zone';

    public const string ACCOUNT_ID = 'account_id';

    public const string CHANNEL = 'channel';

    public const string TYPE = 'type';

    public const string MESSAGE = 'message';

    public const string CONTEXT = 'json_context';

    public const string IP = 'ip';

    public const string CREATED_AT = 'created_at';

    /** Najdłuższa zapisywana treść powiadomienia. */
    public const int MESSAGE_LENGTH = 1000;

    public function __construct(
        private DatabaseManager $connections,
    ) {}

    /**
     * Dopisuje zdarzenie konta strefy.
     *
     * @param string $type Typ w kanale (np. `sign_in`, `status`).
     * @param string|null $message Gotowa treść (powiadomienie); null — opis z typu (`event_log.types.<typ>`).
     * @param array<string, scalar|null> $context Wartości do opisu i szczegółów.
     *
     * @throws JsonException
     */
    public function record(string $zone, int|string $accountId, EventChannel $channel, string $type, ?string $message = null, array $context = [], ?string $ip = null): void
    {
        $this->connections->connection()->table(self::TABLE)->insert([
            self::ZONE => $zone,
            self::ACCOUNT_ID => $accountId,
            self::CHANNEL => $channel->value,
            self::TYPE => $type,
            self::MESSAGE => $message === null ? null : mb_substr($message, 0, self::MESSAGE_LENGTH),
            self::CONTEXT => $context === [] ? null : json_encode($context, JSON_THROW_ON_ERROR),
            self::IP => $ip,
            self::CREATED_AT => Carbon::now(),
        ]);
    }
}
