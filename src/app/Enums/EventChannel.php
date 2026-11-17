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

namespace Simplysmart\Polygon\Enums;

use Simplysmart\Polygon\Localization\PackageText;

/**
 * Kanał zdarzenia w dzienniku konta (EventLog, docs/baza-danych.md → „Dziennik zdarzeń konta”): skąd pochodzi wpis — zmiana danych z tabel
 * historii (`history`), powiadomienie pokazane kontu (`notification`), zdarzenie bezpieczeństwa logowania
 * (`security`) albo wywołanie API (`api`, na przyszłość). Teksty: `enums.event_channel.<value>`.
 */
enum EventChannel: string
{
    case History = 'history';
    case Notification = 'notification';
    case Security = 'security';
    case Api = 'api';

    public function label(): string
    {
        return PackageText::get('enums.event_channel.' . $this->value);
    }
}
