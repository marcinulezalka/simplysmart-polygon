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
        'invalid_range' => 'Podaj numer umowy, poprawne okna (dzień, od przed do, bez nakładania się w jednym dniu) i okres, który nie kończy się przed początkiem.',
        'resource_not_owned' => 'Zasób nie należy do tej organizacji ani jej organizacji nadrzędnej.',
        'lessee_in_owner_tree' => 'Najemca nie może należeć do drzewa organizacji właściciela.',
        'not_cooperation' => 'Wskaż aktywną podorganizację kooperacji najemcy.',
        'contract_unavailable' => 'Umowa kontrahenta nie należy do tej organizacji.',
        'overlapping' => 'Okna nakładają się na inny najem tego zasobu w tym okresie.',
        'resource_scheduled' => 'Zasób ma wtedy grafik innej jednostki — najpierw go zmień.',
        'schedule_outside' => 'Grafik najemcy wychodzi poza nowe okna albo okres umowy — najpierw go zmień.',
    ],
];
