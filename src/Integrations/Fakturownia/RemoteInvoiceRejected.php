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

namespace Simplysmart\Polygon\Integrations\Fakturownia;

use RuntimeException;

/**
 * Faktura z Fakturowni, której nie da się zapisać lokalnie (RemoteInvoiceMapper::read()) — komunikat to powód
 * pominięcia w raporcie synchronizacji.
 */
final class RemoteInvoiceRejected extends RuntimeException {}
