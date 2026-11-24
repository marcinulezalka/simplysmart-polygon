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

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Simplysmart\Polygon\Contracts\CurrentZone;
use Simplysmart\Polygon\Http\DocumentationRoutes;

/**
 * Wejście do Wiki, Docs albo Preview bez hasha sesji w strefie z `url_key`: zalogowany trafia na stronę z hashem swojej
 * sesji.
 */
final readonly class DocumentationEntryController
{
    public function __construct(
        private Redirector $redirect,
        private CurrentZone $zone,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $route = $this->zone->get()->routeName(DocumentationRoutes::showName(DocumentationRoutes::system($request)));

        return $this->redirect->route($route, DocumentationRoutes::parameters(DocumentationRoutes::page($request)));
    }
}
