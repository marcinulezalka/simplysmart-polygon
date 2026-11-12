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

namespace Simplysmart\Polygon\Notifications;

/**
 * Jedno powiadomienie (FlashNotifications::current()): treść i rodzaj (NotificationKind). Bez informacji o sposobie
 * prezentacji — toast i historia dzwonka strefy dostają ten sam obiekt (FlashNotifications::toJson()).
 */
final readonly class Notification
{
    public function __construct(
        public string $text,
        public NotificationKind $kind,
    ) {}
}
