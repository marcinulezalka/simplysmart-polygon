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

namespace Simplysmart\Polygon\Deploy\Console;

/**
 * Pytanie tak/nie do osoby uruchamiającej polecenie.
 */
interface Confirmation
{
    /**
     * @param  string  $question  Pytanie bez odpowiedzi, np. „Wdrożyć też na produkcję? [t/N] ”.
     * @return bool Czy odpowiedź jest twierdząca.
     */
    public function ask(string $question): bool;
}
