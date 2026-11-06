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

namespace Simplysmart\Polygon\Tests\Deploy\Support;

use Simplysmart\Polygon\Deploy\Console\Output;
use RuntimeException;

/**
 * Output zapisujący do pamięci, z odczytem tego, co trafiło na strumień komunikatów i błędów.
 */
final readonly class OutputBuffer
{
    public Output $output;

    /** @var resource */
    private mixed $stdout;

    /** @var resource */
    private mixed $stderr;

    public function __construct()
    {
        $this->stdout = self::memory();
        $this->stderr = self::memory();
        $this->output = new Output($this->stdout, $this->stderr);
    }

    public function stdout(): string
    {
        return (string) stream_get_contents($this->stdout, -1, 0);
    }

    public function stderr(): string
    {
        return (string) stream_get_contents($this->stderr, -1, 0);
    }

    /**
     * @return resource
     */
    public static function memory(string $contents = '')
    {
        $stream = fopen('php://memory', 'w+');

        if ($stream === false) {
            throw new RuntimeException('Nie udało się otworzyć strumienia w pamięci.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
