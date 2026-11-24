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

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Blade;
use Simplysmart\Polygon\View\ColorThemePreference;
use Simplysmart\Polygon\View\FontSizePreference;

/**
 * Żywy przykład Preview: kod Blade strony wyrenderowany w osobnym dokumencie ramki (`srcdoc`) z assetami źródła, więc
 * style przykładu i layoutu dokumentacji się nie mieszają. Kod pochodzi z plików paczek (zaufany).
 */
final readonly class PreviewExample
{
    public function __construct(
        private Factory $views,
        private ColorThemePreference $theme,
        private FontSizePreference $fontSize,
    ) {}

    public function html(string $code, DocumentationSource $source, string $title): string
    {
        $document = $this->views->make('polygon::documentation.example', [
            'content' => Blade::render($code, deleteCachedView: true),
            'assets' => $source->assets,
            'theme' => $this->theme->current(),
            'fontSize' => $this->fontSize->current(),
            'title' => $title,
        ])->render();

        return '<iframe class="pg-doc-example" data-pg-doc-example title="' . e($title) . '" srcdoc="' . e($document) . '"></iframe>';
    }
}
