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

namespace Simplysmart\Polygon\Auth;

use Simplysmart\Polygon\Contracts\BlindIndex;

/**
 * Indeks wyszukiwania kont stref po e-mailu (`email_hash`): skrót BlindIndex z adresu w postaci kanonicznej
 * (EmailAddress). Jedyne miejsce tej reguły — zapis konta i wyszukiwanie używają tej samej metody.
 */
final readonly class EmailIndex
{
    public function __construct(
        private BlindIndex $index,
    ) {}

    public function of(string $email): string
    {
        return $this->index->hash(EmailAddress::normalize($email));
    }
}
