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

namespace Simplysmart\Polygon\Calendar;

use Carbon\CarbonImmutable;
use Simplysmart\Polygon\Enums\RoomTakenBy;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\ZoneAccount;

/**
 * Zasób bloku grafiku niedostępny tuż po jego końcu (FreeSlots::roomTakenAfter()): od kiedy ($from), z jakiego powodu
 * ($by), u kogo — wykonawca innego grafiku albo rezerwacji ($performer) i jednostka (inny grafik, najem; $organization).
 */
final readonly class RoomTaken
{
    public function __construct(
        public CarbonImmutable $from,
        public RoomTakenBy $by,
        public ?ZoneAccount $performer = null,
        public ?Organization $organization = null,
    ) {}
}
