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

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Http\DocumentationRoutes;

/**
 * Strona Wiki, Docs albo Preview dla zalogowanego w strefie (Http\DocumentationRoutes): polygon i źródła tej strefy.
 */
final readonly class DocumentationController
{
    public function __construct(
        private DocumentationResponse $response,
        private CurrentZone $zone,
    ) {}

    public function __invoke(Request $request): Response
    {
        return $this->response->make(DocumentationRoutes::system($request), DocumentationRoutes::page($request), $this->zone->get()->name);
    }
}
