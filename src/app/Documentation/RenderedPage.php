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
 * Strona po przetworzeniu Markdown: HTML z niezamienionymi linkami `*.md` i znacznikami przykładów, niezależny od
 * żądania (adresy stref mają hash sesji), więc nadaje się do cache. Postać tablicowa — cache bez serializacji obiektów.
 *
 * @phpstan-type RenderedPageArray array{title: string, html: string, headings: list<array{level: int, id: string, text: string}>, examples: list<string>}
 */
final readonly class RenderedPage
{
    /**
     * @param list<DocumentationHeading> $headings
     * @param list<string> $examples Kod Blade przykładów (indeks — numer znacznika).
     */
    public function __construct(
        public string $title,
        public string $html,
        public array $headings,
        public array $examples,
    ) {}

    /**
     * @param RenderedPageArray $page
     */
    public static function fromArray(array $page): self
    {
        return new self(
            $page['title'],
            $page['html'],
            array_map(static fn(array $heading): DocumentationHeading => new DocumentationHeading($heading['level'], $heading['id'], $heading['text']), $page['headings']),
            $page['examples'],
        );
    }

    /**
     * @return RenderedPageArray
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'html' => $this->html,
            'headings' => array_map(static fn(DocumentationHeading $heading): array => ['level' => $heading->level, 'id' => $heading->id, 'text' => $heading->text], $this->headings),
            'examples' => $this->examples,
        ];
    }
}
