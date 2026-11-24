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

namespace Simplysmart\Polygon\Tests\Documentation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simplysmart\Polygon\Documentation\DocumentationGroup;
use Simplysmart\Polygon\Documentation\DocumentationIndex;
use Simplysmart\Polygon\Documentation\DocumentationItem;

/**
 * Spis bazowy `documentation.md` (Documentation\DocumentationIndex).
 */
final class DocumentationIndexTest extends TestCase
{
    private const string MARKDOWN = "# Wiki\n\n- [Bez grupy](lost.md)\n- ## Konto\n    - [PIN](pin.md)\n    - [Zasada urządzenia ąę](pin.md#reset)\n- ## Pomoc\n    - [Pytania](faq.md)\n    - [Adres zewnętrzny](https://example.com)\n";

    #[Test]
    public function items_belong_to_the_group_above_them(): void
    {
        $this->assertEquals([
            new DocumentationGroup('Konto', [new DocumentationItem('PIN', 'pin'), new DocumentationItem('Zasada urządzenia ąę', 'pin', 'reset')]),
            new DocumentationGroup('Pomoc', [new DocumentationItem('Pytania', 'faq')]),
        ], DocumentationIndex::parse(self::MARKDOWN)->groups);
    }

    #[Test]
    public function a_page_listed_twice_counts_once_in_the_order(): void
    {
        $this->assertSame(['pin', 'faq'], DocumentationIndex::parse(self::MARKDOWN)->slugs());
    }
}
