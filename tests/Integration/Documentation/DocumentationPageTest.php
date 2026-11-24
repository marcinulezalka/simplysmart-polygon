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

namespace Simplysmart\Polygon\Tests\Integration\Documentation;

use PHPUnit\Framework\Attributes\DataProvider;
use Simplysmart\Polygon\Documentation\DocumentationPages;
use Simplysmart\Polygon\Documentation\DocumentationSystem;
use Simplysmart\Polygon\Tests\Fixtures\Zones\AlphaAccount;
use Simplysmart\Polygon\Tests\Integration\Zones\ZoneTestCase;

/**
 * Strony dokumentacji dla zalogowanego w strefie: układ trzech kolumn, linki, zakładki, przykłady, noindex, 404.
 */
class DocumentationPageTest extends ZoneTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownPaths(): array
    {
        return [
            'unknown page' => ['nope'],
            'unknown source' => ['shop/sign-in'],
            'polygon page under its key' => ['polygon/sign-in'],
            'index file' => ['documentation'],
            'parent directory' => ['../composer'],
            'encoded parent directory' => ['..%2Fdocs%2Fdeploy'],
        ];
    }

    public function test_every_page_opens_and_is_not_indexed(): void
    {
        foreach (DocumentationSystem::cases() as $system) {
            foreach ($this->app->make(DocumentationPages::class)->inOrder($system, 'alpha') as $page) {
                $this->get(route('alpha.' . $system->value . '.show', $page->path()))->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
            }
        }
    }

    public function test_a_page_shows_the_index_the_content_and_its_headings(): void
    {
        $page = $this->get('/alpha/wiki/sign-in')
            ->assertOk()
            ->assertSee('<h2 id="limits">', false)
            ->assertSee('<a href="#limits">', false)
            ->assertSee('rel="next"', false);

        $this->assertMatchesRegularExpression('#<a href="[^"]+/wiki/sign-in"\s+aria-current="page"#', (string) $page->getContent());
    }

    public function test_the_system_address_opens_its_first_page(): void
    {
        $this->get('/alpha/wiki')->assertOk()->assertSee('Czym jest Wiki');
    }

    public function test_relative_links_stay_in_the_zone_also_between_systems(): void
    {
        $this->get('/alpha/wiki/getting-started')->assertSee('href="' . e(route('alpha.wiki.show', 'sign-in')) . '"', false);
        $this->get('/alpha/preview/icons')->assertSee('href="' . e(route('alpha.docs.show', 'ikony')) . '"', false);
    }

    public function test_the_tabs_lead_to_every_system_of_the_zone(): void
    {
        $page = $this->get('/alpha/wiki');

        foreach (DocumentationSystem::cases() as $system) {
            $page->assertSee('class="pg-doc__tab" href="' . e(route('alpha.' . $system->value . '.show')) . '"', false);
        }
    }

    public function test_an_example_is_rendered_in_a_frame(): void
    {
        $this->get('/alpha/preview/toasts')
            ->assertOk()
            ->assertSee('data-pg-doc-example', false)
            ->assertSee(e('<div class="pg-toast pg-toast--status">'), false)
            ->assertDontSee('pg-doc-example:0', false);
    }

    #[DataProvider('unknownPaths')]
    public function test_an_address_outside_the_page_list_is_not_found(string $path): void
    {
        $this->get('/alpha/wiki/' . $path)->assertNotFound();
    }
}
