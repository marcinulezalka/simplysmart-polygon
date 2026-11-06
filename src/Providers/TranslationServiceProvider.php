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

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Simplysmart\Polygon\Contracts\InterfaceLocales;
use Simplysmart\Polygon\Contracts\LocalFormatter;
use Simplysmart\Polygon\Contracts\TextTranslator;
use Simplysmart\Polygon\Localization\DictionaryInterfaceLocales;
use Simplysmart\Polygon\Localization\IntlLocalFormatter;
use Simplysmart\Polygon\Localization\PackageText;
use Simplysmart\Polygon\PolygonServiceProvider;
use Simplysmart\Polygon\Translation\CascadeTextTranslator;
use Simplysmart\Polygon\Translation\CoreTranslationPath;

/**
 * Tłumaczenia i lokalizacja (docs/tlumaczenia.md): teksty `polygon::`, polskie teksty frameworka z `lang/` paczki
 * między frameworkiem a `lang/` projektu (CoreTranslationPath), kaskada TextTranslator dla helperów `text_*`,
 * formatowanie `local_*` i języki interfejsu ze słownika.
 */
final class TranslationServiceProvider extends ServiceProvider
{
    private const string LANG = PolygonServiceProvider::ROOT . '/lang';

    public function register(): void
    {
        $this->app->extend('translation.loader', fn(Loader $loader): Loader => (new CoreTranslationPath(
            $this->app->make(Filesystem::class),
            self::LANG,
            $this->app->langPath(),
        ))->extend($loader));
        $this->app->bind(TextTranslator::class, CascadeTextTranslator::class);
        $this->app->bind(LocalFormatter::class, IntlLocalFormatter::class);
        $this->app->bind(InterfaceLocales::class, DictionaryInterfaceLocales::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(self::LANG, PackageText::NAMESPACE);
    }
}
