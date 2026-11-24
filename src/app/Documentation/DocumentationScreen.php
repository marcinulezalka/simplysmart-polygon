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

namespace Simplysmart\Polygon\Documentation;

/**
 * Dane widoku strony dokumentacji: spis bazowy (kolumna 1), treść (2), spis treści strony (3), zakładki systemów
 * i sąsiednie strony.
 */
final readonly class DocumentationScreen
{
    public function __construct(
        private DocumentationSources $sources,
        private DocumentationPages $pages,
        private PageLoader $loader,
        private DocumentationUrls $urls,
        private LinkRewriter $links,
        private PreviewExample $examples,
    ) {}

    /**
     * @return array{system: DocumentationSystem, title: string, html: string, headings: list<DocumentationHeading>, groups: list<array{title: string, items: list<array{title: string, url: string, current: bool}>}>, previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null, tabs: array<string, string>}
     */
    public function data(DocumentationPage $page, string $zone): array
    {
        $rendered = $this->loader->load($page);
        [$previous, $next] = $this->neighbours($page, $zone);

        return [
            'system' => $page->source->system,
            'title' => $rendered->title,
            'html' => $this->html($rendered, $page),
            'headings' => $rendered->headings,
            'groups' => $this->groups($page, $zone),
            'previous' => $previous,
            'next' => $next,
            'tabs' => $this->tabs(),
        ];
    }

    private function html(RenderedPage $rendered, DocumentationPage $page): string
    {
        $html = $this->links->rewrite($rendered->html, $page->source);

        return (string) preg_replace_callback(MarkdownRenderer::EXAMPLE_PATTERN, fn(array $marker): string => $this->examples->html($rendered->examples[(int) $marker[1]] ?? '', $page->source, $rendered->title), $html);
    }

    /**
     * Zaznaczona jest pierwsza pozycja bieżącej strony.
     *
     * @return list<array{title: string, items: list<array{title: string, url: string, current: bool}>}>
     */
    private function groups(DocumentationPage $page, string $zone): array
    {
        $groups = [];
        $marked = false;

        foreach ($this->sources->visible($page->source->system, $zone) as $source) {
            foreach ($source->index()->groups as $group) {
                $items = [];

                foreach ($group->items as $item) {
                    $current = ! $marked && $source->pagePath($item->slug) === $page->path();
                    $marked = $marked || $current;
                    $items[] = ['title' => $item->title, 'url' => (string) $this->urls->page($source->system, $source->pagePath($item->slug), $item->anchor), 'current' => $current];
                }

                $groups[] = ['title' => $group->title, 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * Poprzednia i następna strona w kolejności spisów.
     *
     * @return array{array{title: string, url: string}|null, array{title: string, url: string}|null}
     */
    private function neighbours(DocumentationPage $page, string $zone): array
    {
        $pages = $this->pages->inOrder($page->source->system, $zone);
        $position = array_search($page->path(), array_map(static fn(DocumentationPage $candidate): string => $candidate->path(), $pages), true);

        if (! is_int($position)) {
            return [null, null];
        }

        return [$this->neighbour($pages[$position - 1] ?? null), $this->neighbour($pages[$position + 1] ?? null)];
    }

    /**
     * @return array{title: string, url: string}|null
     */
    private function neighbour(?DocumentationPage $page): ?array
    {
        return $page === null ? null : ['title' => $this->loader->load($page)->title, 'url' => (string) $this->urls->page($page->source->system, $page->path())];
    }

    /**
     * Adresy zakładek: system => początek systemu w bieżącej strefie.
     *
     * @return array<string, string>
     */
    private function tabs(): array
    {
        $tabs = [];

        foreach (DocumentationSystem::cases() as $system) {
            $tabs[$system->value] = (string) $this->urls->page($system);
        }

        return $tabs;
    }
}
