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
 * Spis bazowy systemu (`documentation.md`, jak w docs Laravela): pozycje `- ## Grupa`, a pod nimi
 * `- [Tytuł](strona.md)` albo `- [Tytuł](strona.md#kotwica)`.
 */
final readonly class DocumentationIndex
{
    private const string GROUP_PATTERN = '/^\s*-\s+##\s+(?<title>.+?)\s*$/';

    private const string ITEM_PATTERN = '/^\s*-\s+\[(?<title>[^\]]+)\]\((?<slug>[a-z0-9-]+)\.md(?:#(?<anchor>[^)\s]+))?\)\s*$/';

    /**
     * @param list<DocumentationGroup> $groups
     */
    private function __construct(
        public array $groups,
    ) {}

    /**
     * Pozycje przed pierwszą grupą i inne linie są pomijane.
     */
    public static function parse(string $markdown): self
    {
        $titles = [];
        $items = [];

        foreach (preg_split('/\R/u', $markdown) ?: [] as $line) {
            if (preg_match(self::GROUP_PATTERN, $line, $match) === 1) {
                $titles[] = $match['title'];
                $items[] = [];
            } elseif ($titles !== [] && preg_match(self::ITEM_PATTERN, $line, $match) === 1) {
                $items[count($titles) - 1][] = new DocumentationItem($match['title'], $match['slug'], $match['anchor'] ?? null);
            }
        }

        return new self(array_map(static fn(string $title, array $group): DocumentationGroup => new DocumentationGroup($title, $group), $titles, $items));
    }

    /**
     * Nazwy stron w kolejności spisu, bez powtórzeń.
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        $slugs = [];

        foreach ($this->groups as $group) {
            foreach ($group->items as $item) {
                $slugs[$item->slug] = $item->slug;
            }
        }

        return array_values($slugs);
    }
}
