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

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

/**
 * Strona Markdown w konwencji docs Laravela → RenderedPage: kotwice `<a name="…"></a>` przed nagłówkami stają się ich
 * `id` (nagłówek bez kotwicy — slug jak na GitHubie), lista spisu treści pod tytułem znika (spis to kolumna 3),
 * `> [!NOTE]` to alert, a blok ` ```blade preview ` — przykład Preview przed swoim kodem. Pliki są zaufane (repozytoria paczek),
 * więc HTML w Markdown zostaje.
 */
final readonly class MarkdownRenderer
{
    /** Wersja przetwarzania — część klucza cache; zmień razem z wynikiem. */
    public const int VERSION = 1;

    /** Słowo w opisie bloku kodu, które robi z niego przykład. */
    private const string EXAMPLE_WORD = 'preview';

    private const string EXAMPLE_MARKER = '<!--pg-doc-example:%d-->';

    public const string EXAMPLE_PATTERN = '/<!--pg-doc-example:(\d+)-->/';

    private const string ANCHOR_PATTERN = '/^<a\s+name="([^"]+)">$/';

    private const string ALERT_PATTERN = '~<blockquote>\s*<p>\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*~';

    /** Poziomy nagłówków w spisie treści strony. */
    private const array LISTED_LEVELS = [2, 3];

    private MarkdownParser $parser;

    private HtmlRenderer $renderer;

    public function __construct()
    {
        $environment = new Environment(['html_input' => 'allow', 'allow_unsafe_links' => false]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $this->parser = new MarkdownParser($environment);
        $this->renderer = new HtmlRenderer($environment);
    }

    /**
     * @param array<string, string> $alertLabels Nagłówki alertów według rodzaju (`note`, `tip`, `important`, `warning`, `caution`).
     */
    public function render(string $markdown, array $alertLabels = []): RenderedPage
    {
        $document = $this->parser->parse($markdown);
        $title = $this->removeTableOfContents($document);
        $headings = $this->identifyHeadings($document);
        $examples = $this->markExamples($document);
        $html = $this->renderer->renderDocument($document)->getContent();

        return new RenderedPage($title, $this->alerts($html, $alertLabels), $headings, $examples);
    }

    /**
     * Usuwa listę linków do kotwic tuż pod tytułem; zwraca tytuł strony.
     */
    private function removeTableOfContents(Document $document): string
    {
        $title = $document->firstChild();

        if (! $title instanceof Heading || $title->getLevel() !== 1) {
            return '';
        }

        $list = $title->next();

        if ($list instanceof ListBlock && $this->linksOnlyToAnchors($list)) {
            $list->detach();
        }

        return $this->text($title);
    }

    private function linksOnlyToAnchors(ListBlock $list): bool
    {
        $links = 0;

        foreach ($list->iterator() as $node) {
            if ($node instanceof Link) {
                if (! str_starts_with($node->getUrl(), '#')) {
                    return false;
                }

                $links++;
            }
        }

        return $links > 0;
    }

    /**
     * Nadaje nagłówkom `id` i zwraca nagłówki spisu treści.
     *
     * @return list<DocumentationHeading>
     */
    private function identifyHeadings(Document $document): array
    {
        $headings = [];
        $used = [];

        foreach ($this->nodes($document, Heading::class) as $heading) {
            $text = $this->text($heading);
            $id = $this->anchor($heading) ?? $this->slug($text, $used);
            $used[$id] = ($used[$id] ?? -1) + 1;
            $heading->data->set('attributes/id', $id);

            if (in_array($heading->getLevel(), self::LISTED_LEVELS, true)) {
                $headings[] = new DocumentationHeading($heading->getLevel(), $id, $text);
            }
        }

        return $headings;
    }

    /**
     * Kotwica z akapitu `<a name="…"></a>` przed nagłówkiem (akapit znika).
     */
    private function anchor(Heading $heading): ?string
    {
        $paragraph = $heading->previous();

        if (! $paragraph instanceof Paragraph) {
            return null;
        }

        $opening = $paragraph->firstChild();

        if (! $opening instanceof HtmlInline || $opening->next() !== $paragraph->lastChild() || preg_match(self::ANCHOR_PATTERN, $opening->getLiteral(), $match) !== 1) {
            return null;
        }

        $paragraph->detach();

        return $match[1];
    }

    /**
     * Slug nagłówka jak na GitHubie; powtórzony dostaje numer.
     *
     * @param array<string, int> $used
     */
    private function slug(string $text, array $used): string
    {
        $slug = str_replace(' ', '-', (string) preg_replace('/[^\p{L}\p{N}\p{M}\p{Pc} -]+/u', '', mb_strtolower($text)));

        return isset($used[$slug]) ? $slug . '-' . ($used[$slug] + 1) : $slug;
    }

    /**
     * Wstawia znaczniki przykładów przed ich kodem i zwraca kod przykładów.
     *
     * @return list<string>
     */
    private function markExamples(Document $document): array
    {
        $examples = [];

        foreach ($this->nodes($document, FencedCode::class) as $code) {
            if (in_array(self::EXAMPLE_WORD, $code->getInfoWords(), true)) {
                $marker = new HtmlBlock(HtmlBlock::TYPE_2_COMMENT);
                $marker->setLiteral(sprintf(self::EXAMPLE_MARKER, count($examples)));
                $code->insertBefore($marker);
                $examples[] = $code->getLiteral();
            }
        }

        return $examples;
    }

    /**
     * @param array<string, string> $labels
     */
    private function alerts(string $html, array $labels): string
    {
        return (string) preg_replace_callback(self::ALERT_PATTERN, static function (array $match) use ($labels): string {
            $kind = strtolower($match[1]);

            return '<blockquote class="pg-doc-alert pg-doc-alert--' . $kind . '"><p class="pg-doc-alert__title">' . htmlspecialchars($labels[$kind] ?? ucfirst($kind)) . '</p><p>';
        }, $html);
    }

    private function text(Node $node): string
    {
        return StringContainerHelper::getChildText($node, [HtmlInline::class]);
    }

    /**
     * Węzły klasy w kolejności dokumentu, zebrane przed zmianami drzewa.
     *
     * @template TNode of Node
     *
     * @param class-string<TNode> $class
     *
     * @return list<TNode>
     */
    private function nodes(Document $document, string $class): array
    {
        $nodes = [];

        foreach ($document->iterator() as $node) {
            if ($node instanceof $class) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }
}
