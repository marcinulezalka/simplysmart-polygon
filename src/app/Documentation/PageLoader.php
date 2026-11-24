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

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Simplysmart\Polygon\Localization\PackageText;

/**
 * Przetworzona strona z cache (klucz: plik, czas zmiany, język, wersja MarkdownRenderer).
 */
final readonly class PageLoader
{
    private const string CACHE_PREFIX = 'polygon.documentation.';

    /** Czas życia wpisu (sekundy): zmiana pliku zmienia klucz, stary wpis wygasa sam. */
    private const int CACHE_SECONDS = 86_400;

    /** Rodzaje alertów (klucze `documentation.alerts.*`). */
    private const array ALERTS = ['note', 'tip', 'important', 'warning', 'caution'];

    public function __construct(
        private MarkdownRenderer $renderer,
        private Repository $cache,
        private Application $app,
    ) {}

    public function load(DocumentationPage $page): RenderedPage
    {
        $key = self::CACHE_PREFIX . hash('xxh128', implode('|', [$page->file, (string) filemtime($page->file), $this->app->getLocale(), (string) MarkdownRenderer::VERSION]));

        $rendered = $this->cache->remember($key, self::CACHE_SECONDS, fn(): array => $this->renderer->render((string) file_get_contents($page->file), $this->alertLabels())->toArray());

        return RenderedPage::fromArray($rendered);
    }

    /**
     * @return array<string, string>
     */
    private function alertLabels(): array
    {
        $labels = [];

        foreach (self::ALERTS as $kind) {
            $labels[$kind] = PackageText::get('documentation.alerts.' . $kind);
        }

        return $labels;
    }
}
