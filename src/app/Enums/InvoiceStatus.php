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
 * Stan faktury (kolumna `status` tabeli `invoices`): robocza, w kolejce do wysyłki, wysłana do systemu fakturowego
 * (ma numer i identyfikatory zewnętrzne) albo błąd wysyłki (komunikat w `error`, wysyłkę można ponowić).
 * Teksty: `enums.invoice_status.<value>`.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    /**
     * Czy fakturę w tym stanie wolno (ponownie) wysłać: w kolejce albo po błędzie; wysłanej się nie powtarza.
     */
    public function canSend(): bool
    {
        return $this === self::Queued || $this === self::Failed;
    }

    public function label(): string
    {
        return PackageText::get('enums.invoice_status.' . $this->value);
    }
}
