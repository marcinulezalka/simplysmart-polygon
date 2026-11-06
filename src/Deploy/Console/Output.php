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
 * Wyjście poleceń: komunikaty na strumień wyjścia, błędy i ostrzeżenia na strumień błędów, każdy w osobnej linii.
 */
final readonly class Output
{
    /**
     * @param  resource  $stdout  Strumień komunikatów.
     * @param  resource  $stderr  Strumień błędów.
     */
    public function __construct(
        private mixed $stdout,
        private mixed $stderr,
    ) {}

    /**
     * Wyjście procesu: STDOUT i STDERR.
     */
    public static function standard(): self
    {
        return new self(STDOUT, STDERR);
    }

    public function line(string $message): void
    {
        fwrite($this->stdout, $message . "\n");
    }

    /**
     * Tekst bez dopisanego końca linii (np. pytanie, na które odpowiedź pada w tej samej linii).
     */
    public function write(string $text): void
    {
        fwrite($this->stdout, $text);
    }

    public function error(string $message): void
    {
        fwrite($this->stderr, $message . "\n");
    }
}
