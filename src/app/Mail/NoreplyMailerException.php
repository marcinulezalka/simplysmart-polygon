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

namespace Simplysmart\Polygon\Mail;

use RuntimeException;

/**
 * Błąd wysyłki przez nadawcę „noreply” (Mail\NoreplyMailer): komunikat bezpieczny do pokazania w interfejsie,
 * bez szczegółów serwera ani danych logowania.
 */
final class NoreplyMailerException extends RuntimeException {}
