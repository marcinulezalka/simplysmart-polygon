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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Sortowanie listy po kolumnie (Listing\SortsByColumn): kolumny, kierunek, kolumna i przełączenie nagłówka.
 */
final class SortsByColumnTest extends TestCase
{
    #[Test]
    public function columns_are_the_ascending_cases_in_order(): void
    {
        $this->assertSame([FixtureSort::Name, FixtureSort::Date], FixtureSort::columns());
        $this->assertSame('sort', FixtureSort::PARAMETER);
    }

    #[Test]
    public function a_descending_case_knows_its_column(): void
    {
        $this->assertTrue(FixtureSort::DateDesc->isDescending());
        $this->assertFalse(FixtureSort::Date->isDescending());
        $this->assertSame(FixtureSort::Date, FixtureSort::DateDesc->column());
    }

    #[Test]
    public function the_header_sorts_ascending_first_and_descending_on_the_second_click(): void
    {
        $this->assertSame(FixtureSort::Date, FixtureSort::Date->toggled(null));
        $this->assertSame(FixtureSort::Date, FixtureSort::Date->toggled(FixtureSort::Name));
        $this->assertSame(FixtureSort::DateDesc, FixtureSort::Date->toggled(FixtureSort::Date));
        $this->assertSame(FixtureSort::Date, FixtureSort::Date->toggled(FixtureSort::DateDesc));
    }
}
