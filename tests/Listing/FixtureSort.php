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

namespace Simplysmart\Polygon\Tests\Listing;

use Simplysmart\Polygon\Listing\SortableColumn;
use Simplysmart\Polygon\Listing\SortsByColumn;

/**
 * Enum testowy: dwie kolumny w obu kierunkach.
 */
enum FixtureSort: string implements SortableColumn
{
    use SortsByColumn;

    case Name = 'name';
    case NameDesc = '-name';
    case Date = 'date';
    case DateDesc = '-date';

    public function label(): string
    {
        return $this->column()->name;
    }
}
