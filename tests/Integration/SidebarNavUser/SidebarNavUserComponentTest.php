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

namespace Simplysmart\Polygon\Tests\Integration\SidebarNavUser;

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Pozycja użytkownika w nawigacji z ikon (`<x-polygon::sidebar-nav-user>`): link jak każda pozycja, z avatarem zamiast ikony.
 */
class SidebarNavUserComponentTest extends TestCase
{
    public function test_it_is_a_navigation_item_with_an_avatar_instead_of_an_icon(): void
    {
        $html = Blade::render('<x-polygon::sidebar-nav-user href="/konto" label="Anna Nowak" initials="AN" photo="/zdjecie" color="#b45309" :current="true" data-zone="x" />');
        $item = HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelector('a.pg-sidebar-nav-icon__item.pg-sidebar-nav-user');

        $this->assertNotNull($item);
        $this->assertSame(
            ['/konto', 'Anna Nowak', 'page', 'x'],
            array_map(static fn(string $name): ?string => $item->getAttribute($name), ['href', 'title', 'aria-current', 'data-zone']),
        );
        $this->assertStringContainsString('#b45309', (string) $item->querySelector('.pg-avatar[aria-hidden="true"]')?->getAttribute('style'));
        $this->assertSame('/zdjecie', $item->querySelector('.pg-avatar img')?->getAttribute('src'));
        $this->assertNull($item->querySelector('.pg-sidebar-nav-icon__icon'));
    }
}
