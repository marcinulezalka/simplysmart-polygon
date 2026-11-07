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
        'invalid_range' => 'Przedział musi mieścić co najmniej jeden termin, a okres ważności nie może kończyć się przed początkiem.',
        'resource_unavailable' => 'Zasób nie należy do jednostki ani jej organizacji nadrzędnej.',
        'resource_taken' => 'Zasób jest wtedy zajęty przez inny grafik.',
        'performer_busy' => 'Wykonawca jest wtedy w innym miejscu grafiku.',
        'leased_out' => 'Zasób jest wtedy wynajęty innej organizacji.',
        'outside_lease' => 'Grafik na wynajętym zasobie musi mieścić się w oknach i okresie umowy najmu (przy umowie z końcem podaj koniec grafiku).',
    ],
    'has_bookings' => 'W tym grafiku są przyszłe rezerwacje — najpierw je odwołaj.',
    'slot_unavailable' => 'Ten termin nie jest już wolny — wybierz inny.',
    'transition_rejected' => 'Tej zmiany stanu rezerwacji nie można wykonać.',
];
