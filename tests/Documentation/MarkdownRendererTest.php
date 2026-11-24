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
use Simplysmart\Polygon\Documentation\DocumentationHeading;
use Simplysmart\Polygon\Documentation\MarkdownRenderer;
use Simplysmart\Polygon\Documentation\RenderedPage;

/**
 * Strona Markdown w konwencji docs Laravela (Documentation\MarkdownRenderer).
 */
final class MarkdownRendererTest extends TestCase
{
    #[Test]
    public function the_contents_list_under_the_title_is_removed(): void
    {
        $page = $this->render("# Tytuł\n\n- [Sekcja](#section)\n\nWstęp.");

        $this->assertSame('Tytuł', $page->title);
        $this->assertStringNotContainsString('<ul>', $page->html);
        $this->assertStringContainsString('Wstęp.', $page->html);
    }

    #[Test]
    public function a_list_under_the_title_with_other_links_stays(): void
    {
        $this->assertStringContainsString('<ul>', $this->render("# Tytuł\n\n- [Sekcja](#section)\n- [Strona](page.md)")->html);
    }

    #[Test]
    public function the_anchor_before_a_heading_becomes_its_id(): void
    {
        $page = $this->render("# T\n\n<a name=\"how\"></a>\n## Jak to działa");

        $this->assertStringContainsString('<h2 id="how">Jak to działa</h2>', $page->html);
        $this->assertStringNotContainsString('<a name', $page->html);
    }

    #[Test]
    public function headings_without_anchors_get_github_slugs(): void
    {
        $page = $this->render("# T\n\n## 15. Uprawnienia (`spatie/x`, zespół = organizacja)\n\n## Trasy\n\n### Trasy\n\n#### Pominięty");

        $this->assertEquals([
            new DocumentationHeading(2, '15-uprawnienia-spatiex-zespół--organizacja', '15. Uprawnienia (spatie/x, zespół = organizacja)'),
            new DocumentationHeading(2, 'trasy', 'Trasy'),
            new DocumentationHeading(3, 'trasy-1', 'Trasy'),
        ], $page->headings);
    }

    #[Test]
    public function a_github_alert_gets_its_kind_and_label(): void
    {
        $html = $this->render("> [!WARNING]\n> Uważaj.", ['warning' => 'Ostrzeżenie'])->html;

        $this->assertStringContainsString('<blockquote class="pg-doc-alert pg-doc-alert--warning"><p class="pg-doc-alert__title">Ostrzeżenie</p><p>Uważaj.</p>', $html);
        $this->assertStringContainsString('<blockquote>', $this->render('> Zwykły cytat.')->html);
    }

    #[Test]
    public function a_preview_block_becomes_an_example_before_its_code(): void
    {
        $page = $this->render("```php\n\$a;\n```\n\n```blade preview\n<b>Raz</b>\n```");

        $this->assertSame(["<b>Raz</b>\n"], $page->examples);
        $this->assertMatchesRegularExpression('/<!--pg-doc-example:0-->\s*<pre><code class="language-blade">&lt;b&gt;Raz/', $page->html);
    }

    #[Test]
    public function the_array_form_keeps_the_whole_page(): void
    {
        $page = $this->render("# T\n\n## A\n\n```blade preview\nx\n```");

        $this->assertEquals($page, RenderedPage::fromArray($page->toArray()));
    }

    /**
     * @param array<string, string> $labels
     */
    private function render(string $markdown, array $labels = []): RenderedPage
    {
        return (new MarkdownRenderer())->render($markdown, $labels);
    }
}
