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

namespace Simplysmart\Polygon\Install;

use Simplysmart\Polygon\Enums\ResourceKind;

/**
 * Zasób organizacji z pliku instalacji po walidacji (`organizations.<klucz>.resources[]`): klucz z pliku
 * (identyfikator zasobu założonego przez instalację w `json_attributes.install_key`, unikalny w organizacji), nazwa,
 * rodzaj i opcjonalny kod.
 */
final readonly class InstallResource
{
    public function __construct(
        public string $key,
        public string $name,
        public ResourceKind $kind,
        public ?string $code,
    ) {}
}
