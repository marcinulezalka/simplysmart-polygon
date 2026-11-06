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

namespace Simplysmart\Polygon\Tests\Deploy\Console;

use Simplysmart\Polygon\Deploy\Console\TerminalConfirmation;
use Simplysmart\Polygon\Tests\Deploy\Support\OutputBuffer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pytanie tak/nie w terminalu: odpowiedzi twierdzące i przeczące oraz brak terminala.
 */
final class TerminalConfirmationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function answers(): iterable
    {
        yield 't' => ["t\n", true];
        yield 'TAK wielkimi literami' => ["TAK\n", true];
        yield 'yes ze spacjami' => ["  yes \n", true];
        yield 'n' => ["n\n", false];
        yield 'pusta odpowiedź' => ["\n", false];
        yield 'koniec wejścia' => ['', false];
    }

    #[Test]
    #[DataProvider('answers')]
    public function interactive_answer_decides(string $input, bool $expected): void
    {
        $buffer = new OutputBuffer();
        $confirmation = new TerminalConfirmation(OutputBuffer::memory($input), $buffer->output, interactive: true);

        $this->assertSame($expected, $confirmation->ask('Wdrożyć? '));
        $this->assertSame('Wdrożyć? ', $buffer->stdout());
    }

    #[Test]
    public function without_a_terminal_the_answer_is_no(): void
    {
        $buffer = new OutputBuffer();
        $confirmation = new TerminalConfirmation(OutputBuffer::memory("t\n"), $buffer->output, interactive: false);

        $this->assertFalse($confirmation->ask('Wdrożyć? '));
        $this->assertSame('Wdrożyć? ' . TerminalConfirmation::NOT_INTERACTIVE . "\n", $buffer->stdout());
    }
}
