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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simplysmart\Polygon\Documentation\DocumentationSource;
use Simplysmart\Polygon\Documentation\DocumentationSources;
use Simplysmart\Polygon\Documentation\DocumentationSystem;
use Simplysmart\Polygon\Documentation\MarkdownRenderer;

/**
 * Kompletność dokumentacji polygonu: każda strona jest w spisie, a każdy link prowadzi do istniejącej strony i kotwicy.
 */
final class DocumentationContentTest extends TestCase
{
    /**
     * @return array<string, array{DocumentationSystem}>
     */
    public static function systems(): array
    {
        return array_column(array_map(static fn(DocumentationSystem $system): array => [$system->value, [$system]], DocumentationSystem::cases()), 1, 0);
    }

    #[Test]
    #[DataProvider('systems')]
    public function the_index_lists_every_page(DocumentationSystem $system): void
    {
        $source = $this->source($system);

        $this->assertEqualsCanonicalizing(array_keys($source->files()), $source->index()->slugs());
    }

    #[Test]
    #[DataProvider('systems')]
    public function index_items_and_page_links_lead_to_existing_headings(DocumentationSystem $system): void
    {
        $broken = [];

        foreach ($this->source($system)->index()->groups as $group) {
            foreach ($group->items as $item) {
                $broken = [...$broken, ...$this->broken($system, $item->slug, $item->anchor, 'documentation')];
            }
        }

        foreach ($this->source($system)->files() as $slug => $file) {
            preg_match_all('~<a href="(?:\.\./(wiki|docs|preview)/)?([a-z0-9-]+)\.md(?:\#([^"]*))?">~', $this->html($file), $links, PREG_SET_ORDER);

            foreach ($links as $link) {
                $broken = [...$broken, ...$this->broken(DocumentationSystem::tryFrom($link[1]) ?? $system, $link[2], isset($link[3]) ? rawurldecode($link[3]) : null, $slug)];
            }
        }

        $this->assertSame([], $broken);
    }

    /**
     * @return list<string>
     */
    private function broken(DocumentationSystem $system, string $slug, ?string $anchor, string $from): array
    {
        $file = $this->source($system)->file($slug);
        $found = $file !== null && ($anchor === null || str_contains($this->html($file), ' id="' . $anchor . '"'));

        return $found ? [] : [$from . ' → ' . $system->value . '/' . $slug . '#' . $anchor];
    }

    private function html(string $file): string
    {
        return (new MarkdownRenderer())->render((string) file_get_contents($file))->html;
    }

    private function source(DocumentationSystem $system): DocumentationSource
    {
        return (new DocumentationSources())->visible($system, null)[0];
    }
}
