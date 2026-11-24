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

namespace Simplysmart\Polygon\Http\Controllers\Documentation;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Simplysmart\Polygon\Documentation\DocumentationPages;
use Simplysmart\Polygon\Documentation\DocumentationScreen;
use Simplysmart\Polygon\Documentation\DocumentationSystem;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Odpowiedź ze stroną dokumentacji, wspólna dla kontrolerów systemów.
 */
final readonly class DocumentationResponse
{
    public function __construct(
        private DocumentationPages $pages,
        private DocumentationScreen $screen,
        private ResponseFactory $responses,
    ) {}

    /**
     * Bez adresu strony — pierwsza strona systemu.
     *
     * @throws NotFoundHttpException Gdy strony nie ma albo nie jest widoczna w strefie.
     */
    public function make(DocumentationSystem $system, ?string $path, string $zone): Response
    {
        $page = $path === null ? $this->pages->first($system, $zone) : $this->pages->find($system, $path, $zone);

        if ($page === null) {
            throw new NotFoundHttpException();
        }

        return $this->responses->view('polygon::documentation.page', $this->screen->data($page, $zone), 200, ['X-Robots-Tag' => 'noindex']);
    }
}
