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

namespace Simplysmart\Polygon\Contracts;

/**
 * Języki interfejsu włączone teraz: podzbiór języków z configu (APP_LOCALE + APP_LOCALES, Localization\DictionaryLocales),
 * który wybiera administrator w słowniku języków.
 */
interface InterfaceLocales
{
    /**
     * @return list<string>
     */
    public function enabled(): array;

    public function default(): string;

    public function isEnabled(string $locale): bool;

    /**
     * Zapomina zapamiętany wybór (po zmianie słownika języków).
     */
    public function forget(): void;
}
