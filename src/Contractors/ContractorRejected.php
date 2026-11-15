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

namespace Simplysmart\Polygon\Contractors;

use Simplysmart\Polygon\Validation\FieldRejected;

/**
 * Kontrahent, kontakt albo umowa nie zostały zapisane: błędne pole ($field) z komunikatem do pokazania przy formularzu.
 */
final class ContractorRejected extends FieldRejected {}
