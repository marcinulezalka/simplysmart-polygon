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

namespace Simplysmart\Polygon\Invoicing;

use Simplysmart\Polygon\Integrations\Fakturownia\ClientMapper;
use Simplysmart\Polygon\Models\Contractor;

/**
 * Odcisk danych kontrahenta, które trafiają do systemu fakturowego (pola ClientMapper::toPayload()): zapisany
 * w powiązaniu po synchronizacji (`synced_checksum`). Ten sam odcisk znaczy, że kontrahent nie zmienił się od
 * synchronizacji — nie ma czego wysyłać, więc dane zapisane z pobrania nie wracają do Fakturowni.
 */
final class ContractorFingerprint
{
    public static function of(Contractor $contractor): string
    {
        $payload = ClientMapper::toPayload($contractor);
        ksort($payload);

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
