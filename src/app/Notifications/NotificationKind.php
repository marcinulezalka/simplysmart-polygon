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
 * Rodzaj powiadomienia (treść i styl), niezależny od sposobu prezentacji (toast, pasek nad treścią — dobiera go
 * resources/js/notifications.js strefy): Status — komunikat po akcji (Http\StatusMessage), Danger — błędy
 * walidacji formularza. Nowy rodzaj to nowy przypadek enuma, bez zmian w FlashNotifications ani w JS.
 */
enum NotificationKind: string
{
    case Status = 'status';
    case Danger = 'danger';
}
