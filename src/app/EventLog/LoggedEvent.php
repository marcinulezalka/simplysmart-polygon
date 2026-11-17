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

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Enums\EventChannel;

/**
 * Wpis dziennika zdarzeń konta (Queries\EventLog\AccountEventLog): kanał, typ (np. `sign_in`, `update`), opis do
 * pokazania, chwila, adres IP (gdy znany) i — dla zmian danych — tabela i id zmienionego rekordu.
 */
final readonly class LoggedEvent
{
    public function __construct(
        public EventChannel $channel,
        public string $type,
        public string $label,
        public CarbonImmutable $createdAt,
        public ?string $ip = null,
        public ?string $subject = null,
        public ?int $subjectId = null,
    ) {}
}
