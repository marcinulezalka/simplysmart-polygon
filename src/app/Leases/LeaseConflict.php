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

namespace Simplysmart\Polygon\Leases;

use Simplysmart\Polygon\Localization\PackageText;

/**
 * Powód odrzucenia umowy najmu (SaveLease): błędny okres, okna albo numer; zasób spoza organizacji właściciela; najemca
 * w drzewie właściciela; podorganizacja bez flagi kooperacji albo nie najemcy; umowa kontrahenta spoza właściciela;
 * okna nakładające się na inny najem zasobu; zasób zajęty wtedy przez grafik innej jednostki; grafik najemcy wychodzący
 * poza nowe okna albo okres. Teksty: `leases.conflict.<value>`.
 */
enum LeaseConflict: string
{
    case InvalidRange = 'invalid_range';
    case ResourceNotOwned = 'resource_not_owned';
    case LesseeInOwnerTree = 'lessee_in_owner_tree';
    case NotCooperation = 'not_cooperation';
    case ContractUnavailable = 'contract_unavailable';
    case Overlapping = 'overlapping';
    case ResourceScheduled = 'resource_scheduled';
    case ScheduleOutside = 'schedule_outside';

    public function label(): string
    {
        return PackageText::get('leases.conflict.' . $this->value);
    }
}
