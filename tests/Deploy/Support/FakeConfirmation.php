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

use Simplysmart\Polygon\Deploy\Console\Confirmation;

/**
 * Atrapa pytania tak/nie: zapisuje pytania i odpowiada stałą odpowiedzią.
 */
final class FakeConfirmation implements Confirmation
{
    /**
     * @var list<string>
     */
    public array $questions = [];

    public function __construct(
        public bool $answer = false,
    ) {}

    public function ask(string $question): bool
    {
        $this->questions[] = $question;

        return $this->answer;
    }
}
