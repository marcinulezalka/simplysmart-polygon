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

namespace Simplysmart\Polygon\Http;

/**
 * Komunikat o wyniku akcji po przekierowaniu (sesja flash): kontrolery stref zapisują go pod KEY
 * (`->with(StatusMessage::KEY, $tekst)`), a widoki paczek stref czytają `session(StatusMessage::KEY)`.
 */
final class StatusMessage
{
    public const string KEY = 'status';
}
