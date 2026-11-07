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

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Simplysmart\Polygon\Assets\PackageAssets;
use Simplysmart\Polygon\Console\PublishAssetsCommand;
use Simplysmart\Polygon\View\ColorThemePreference;

/**
 * Assety paczek i tryb kolorystyczny: rejestr Assets\PackageAssets (singleton, paczki dopisują się w providerach),
 * dyrektywa `@packageAssets('<paczka>', [wejścia])`, komenda `polygon:assets` i ciasteczko trybu bez szyfrowania
 * (View\ColorThemePreference).
 */
final class AssetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PackageAssets::class);
    }

    public function boot(): void
    {
        EncryptCookies::except(ColorThemePreference::COOKIE);
        Blade::directive('packageAssets', static fn(string $expression): string => '<?php echo app(' . PackageAssets::class . '::class)->tags(' . $expression . '); ?>');

        if ($this->app->runningInConsole()) {
            $this->commands([PublishAssetsCommand::class]);
        }
    }
}
