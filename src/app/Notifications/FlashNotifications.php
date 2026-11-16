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

use Illuminate\Support\ViewErrorBag;
use Simplysmart\Polygon\Http\StatusMessage;

/**
 * Powiadomienia bieżącego żądania: komunikat statusu po przekierowaniu (Http\StatusMessage, sesja flash) i błędy
 * walidacji ($errors widoku) jako jedna lista Notification — jedyne źródło, z którego strefa buduje toast i
 * historię dzwonka paska nawigacji (partials.messages strefy, resources/js/notifications.js, initNotifications()).
 */
final class FlashNotifications
{
    /**
     * @return list<Notification>
     */
    public function current(ViewErrorBag $errors): array
    {
        $notifications = [];

        $status = session(StatusMessage::KEY);
        if (is_string($status) && $status !== '') {
            $notifications[] = new Notification($status, NotificationKind::Status);
        }

        if ($errors->any()) {
            $notifications[] = new Notification(implode("\n", array_values(array_filter($errors->all(), is_string(...)))), NotificationKind::Danger);
        }

        return $notifications;
    }

    /**
     * @param list<Notification> $notifications
     */
    public function toJson(array $notifications): string
    {
        return json_encode(
            array_map(
                static fn(Notification $notification): array => ['text' => $notification->text, 'kind' => $notification->kind->value],
                $notifications,
            ),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );
    }
}
