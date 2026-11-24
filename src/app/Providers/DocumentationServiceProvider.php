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

namespace Simplysmart\Polygon\Providers;

use Illuminate\Support\ServiceProvider;
use Simplysmart\Polygon\Documentation\DocumentationSources;

/**
 * Wiki, Docs i Preview (docs/dokumentacja.md): rejestr źródeł (singleton — paczki dopisują swoje katalogi) i widoki
 * layoutu dokumentacji — jedyne widoki polygonu.
 */
final class DocumentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DocumentationSources::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(PolygonServiceProvider::ROOT . '/resources/views', 'polygon');
    }
}
