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

return [
    'conflict' => [
        'invalid_range' => 'The range must fit at least one slot and the period cannot end before it starts.',
        'resource_unavailable' => 'The resource belongs neither to the unit nor to its parent organization.',
        'resource_taken' => 'The resource is taken by another schedule at that time.',
        'performer_busy' => 'The performer is scheduled elsewhere at that time.',
    ],
    'has_bookings' => 'There are future bookings in this schedule — cancel them first.',
    'slot_unavailable' => 'This slot is no longer free — choose another one.',
    'transition_rejected' => 'This booking status change is not allowed.',
];
