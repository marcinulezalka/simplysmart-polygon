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
 * Pytanie tak/nie zadawane w terminalu. Bez terminala (CI, potok) odpowiedzią jest zawsze „nie”, więc żadna
 * nieobsługiwana sesja nie potwierdzi niczego przypadkiem.
 */
final readonly class TerminalConfirmation implements Confirmation
{
    /** Odpowiedzi twierdzące (bez rozróżniania wielkości liter). */
    public const array YES = ['t', 'tak', 'y', 'yes'];

    public const string NOT_INTERACTIVE = 'nie (brak terminala)';

    /**
     * @param  resource  $input  Strumień odpowiedzi.
     * @param  bool  $interactive  Czy wejście to terminal.
     */
    public function __construct(
        private mixed $input,
        private Output $output,
        private bool $interactive,
    ) {}

    /**
     * Pytanie na STDIN procesu; interaktywne tylko wtedy, gdy STDIN jest terminalem.
     */
    public static function standard(Output $output): self
    {
        return new self(STDIN, $output, stream_isatty(STDIN));
    }

    public function ask(string $question): bool
    {
        if (! $this->interactive) {
            $this->output->line($question . self::NOT_INTERACTIVE);

            return false;
        }

        $this->output->write($question);

        return in_array(strtolower(trim((string) fgets($this->input))), self::YES, true);
    }
}
