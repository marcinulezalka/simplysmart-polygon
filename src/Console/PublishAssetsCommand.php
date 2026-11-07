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

namespace Simplysmart\Polygon\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Simplysmart\Polygon\Assets\PackageAssets;

/**
 * Udostępnia zbudowane assety paczek (Assets\PackageAssets) pod `public/vendor/<paczka>`: symlink do katalogu
 * `public/` paczki, a z `--copy` kopia (hosting bez symlinków). Istniejący wpis jest zastępowany. Uruchamia ją deploy.
 */
final class PublishAssetsCommand extends Command
{
    protected $signature = 'polygon:assets {--copy : Kopiuje pliki zamiast tworzyć symlinki}';

    protected $description = 'Udostępnia assety paczek pod public/vendor/<paczka>';

    public function handle(PackageAssets $assets, Filesystem $files): int
    {
        $directory = public_path(PackageAssets::PUBLIC_DIRECTORY);
        $files->ensureDirectoryExists($directory);

        foreach ($assets->packages() as $package => $source) {
            $target = $directory . '/' . $package;

            if (is_link($target) || is_file($target)) {
                $files->delete($target);
            } elseif (is_dir($target)) {
                $files->deleteDirectory($target);
            }

            if ($this->option('copy') === true) {
                $files->copyDirectory($source, $target);
            } else {
                $files->link($source, $target);
            }

            $this->components->info("{$package}: public/" . PackageAssets::PUBLIC_DIRECTORY . "/{$package}");
        }

        return self::SUCCESS;
    }
}
