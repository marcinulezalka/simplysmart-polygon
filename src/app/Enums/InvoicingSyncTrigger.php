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
 * Tryb uruchomienia synchronizacji z systemem fakturowym (Invoicing\SyncInvoicing, raport w
 * `invoicing_accounts.last_sync_report`): automatycznie z harmonogramu albo na żądanie z ekranu.
 * Teksty: `enums.invoicing_sync_trigger.<value>`.
 */
enum InvoicingSyncTrigger: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    public function label(): string
    {
        return PackageText::get('enums.invoicing_sync_trigger.' . $this->value);
    }
}
