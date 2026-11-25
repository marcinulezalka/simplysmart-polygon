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

namespace Simplysmart\Polygon\Tests\Integration\SidebarNavIcon;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Nawigacja z samych ikon (`<x-polygon::sidebar-nav-icon>`): nazwy dla czytnika ekranu, dymek pozycji, jedna bieżąca strona,
 * atrybuty strefy, położenie na środku ekranu i przesunięcie zaznaczenia.
 */
class SidebarNavIconComponentTest extends TestCase
{
    public function test_it_renders_named_groups_and_marks_only_the_current_item(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-polygon::sidebar-nav-icon label="Menu" class="zone-nav">
                <x-polygon::sidebar-nav-icon.group label="Praca">
                    <x-polygon::sidebar-nav-icon.item href="/panel" label="Pulpit" :current="true"><svg></svg></x-polygon::sidebar-nav-icon.item>
                    <x-polygon::sidebar-nav-icon.item href="/kalendarz" label="Kalendarz" data-zone="x"><svg></svg></x-polygon::sidebar-nav-icon.item>
                </x-polygon::sidebar-nav-icon.group>
            </x-polygon::sidebar-nav-icon>
            BLADE);

        $document = $this->document($html);
        $nav = $document->querySelector('nav.pg-sidebar-nav-icon.zone-nav');
        $group = $document->querySelector('nav > .pg-sidebar-nav-icon__card > .pg-sidebar-nav-icon__group');
        $current = $document->querySelector('.pg-sidebar-nav-icon__group > a.pg-sidebar-nav-icon__item[href="/panel"]');
        $other = $document->querySelector('.pg-sidebar-nav-icon__group > a.pg-sidebar-nav-icon__item[href="/kalendarz"]');

        $this->assertSame('Menu', $nav?->getAttribute('aria-label'));
        $this->assertSame(['group', 'Praca'], [$group?->getAttribute('role'), $group?->getAttribute('aria-label')]);
        $this->assertSame(['Pulpit', 'page'], $this->attributes($current, ['title', 'aria-current']));
        $this->assertSame(['Kalendarz', null, 'x'], $this->attributes($other, ['title', 'aria-current', 'data-zone']));
        $this->assertSame('Kalendarz', trim($other->textContent ?? ''));
        $this->assertNotNull($other?->querySelector('[aria-hidden="true"] > svg'));
    }

    public function test_it_is_centered_on_the_screen_unless_the_zone_turns_it_off(): void
    {
        $centered = $this->document(Blade::render('<x-polygon::sidebar-nav-icon label="Menu" />'));
        $inPlace = $this->document(Blade::render('<x-polygon::sidebar-nav-icon label="Menu" :centered="false" />'));

        $this->assertNotNull($centered->querySelector('nav.pg-sidebar-nav-icon--centered'));
        $this->assertNull($inPlace->querySelector('nav.pg-sidebar-nav-icon--centered'));
    }

    public function test_the_slide_is_turned_on_by_the_zone(): void
    {
        $slide = '.pg-sidebar-nav-icon--action-slide, [data-pg-sidebar-nav-icon-slide]';
        $on = $this->document(Blade::render('<x-polygon::sidebar-nav-icon label="Menu" action-slide />'));
        $off = $this->document(Blade::render('<x-polygon::sidebar-nav-icon label="Menu" />'));

        $this->assertNotNull($on->querySelector('nav.pg-sidebar-nav-icon--action-slide[data-pg-sidebar-nav-icon-slide]'));
        $this->assertNull($off->querySelector($slide));
    }

    public function test_a_group_without_a_name_has_no_role(): void
    {
        $html = Blade::render('<x-polygon::sidebar-nav-icon.group>x</x-polygon::sidebar-nav-icon.group>');

        $this->assertStringContainsString('<div class="pg-sidebar-nav-icon__group">', $html);
        $this->assertStringNotContainsString('role=', $html);
    }

    private function document(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }

    /**
     * @param list<string> $names
     * @return list<string|null>
     */
    private function attributes(?Element $element, array $names): array
    {
        return array_map(static fn(string $name): ?string => $element?->getAttribute($name), $names);
    }
}
