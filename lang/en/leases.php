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
        'invalid_range' => 'Enter the contract number, valid windows (day, start before end, no overlaps within a day) and a period that does not end before it starts.',
        'resource_not_owned' => 'The resource belongs neither to this organization nor to its parent organization.',
        'lessee_in_owner_tree' => 'The lessee cannot belong to the owner\'s organization tree.',
        'not_cooperation' => 'Choose an active cooperation sub-organization of the lessee.',
        'contract_unavailable' => 'The contractor contract does not belong to this organization.',
        'overlapping' => 'The windows overlap another lease of this resource in this period.',
        'resource_scheduled' => 'Another unit has a schedule on the resource at that time — change it first.',
        'schedule_outside' => 'The lessee\'s schedule falls outside the new windows or period — change it first.',
    ],
];
