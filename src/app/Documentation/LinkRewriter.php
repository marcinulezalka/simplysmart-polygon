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
 * Linki względne stron (`strona.md#kotwica`, `../wiki/strona.md` — działają też na GitHubie) zamieniane na adresy tras;
 * link bez adresu (np. Docs bez jednoznacznej strefy) traci odnośnik, a zostaje sam tekst.
 */
final readonly class LinkRewriter
{
    private const string LINK_PATTERN = '~<a href="(?:\.\./(?<system>wiki|docs|preview)/)?(?<slug>[a-z0-9-]+)\.md(?:\#(?<anchor>[^"]*))?">(?<text>.*?)</a>~s';

    public function __construct(
        private DocumentationUrls $urls,
    ) {}

    public function rewrite(string $html, DocumentationSource $source): string
    {
        return (string) preg_replace_callback(self::LINK_PATTERN, function (array $link) use ($source): string {
            $system = DocumentationSystem::tryFrom($link['system']) ?? $source->system;
            $anchor = $link['anchor'] === '' ? null : $link['anchor'];
            $url = $this->urls->page($system, $source->pagePath($link['slug']), $anchor);

            return $url === null ? $link['text'] : '<a href="' . e($url) . '">' . $link['text'] . '</a>';
        }, $html);
    }
}
