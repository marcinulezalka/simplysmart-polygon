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
use RuntimeException;
use Simplysmart\Polygon\Icons\IconCatalog;
use Simplysmart\Polygon\Icons\IconifyIcon;

/**
 * Narzędzie deweloperskie (docs/ikony.md): zapisuje wycinki zestawów Iconify z podanymi ikonami jako lokalne kolekcje
 * `<katalog>/<zestaw>.json` (format Iconify JSON) — ikony interfejsu strefy ładowane w JS przez addCollection (bez
 * zapytań przeglądarki do API) i składane na serwerze dla PDF (Icons\IconifyCollection). Plik zestawu jest zastępowany
 * w całości, więc podaj wszystkie ikony zestawu, których strefa używa.
 */
final class SubsetIconsCommand extends Command
{
    protected $signature = 'polygon:icons:subset {directory : Katalog kolekcji} {icons* : Ikony „zestaw:ikona”}';

    protected $description = 'Zapisuje wycinki zestawów Iconify z podanymi ikonami jako lokalne pliki <zestaw>.json';

    public function handle(IconCatalog $catalog): int
    {
        $directory = $this->argument('directory');
        /** @var list<string> $values */
        $values = (array) $this->argument('icons');
        $byPrefix = [];

        foreach ($values as $value) {
            $icon = IconifyIcon::tryFrom($value);

            if ($icon === null) {
                $this->error("Nieprawidłowa nazwa ikony: {$value}");

                return self::FAILURE;
            }

            $byPrefix[$icon->prefix][$icon->name] = $icon->name;
        }

        if (! is_string($directory) || (! is_dir($directory) && ! mkdir($directory, 0o755, true))) {
            $this->error('Nie można utworzyć katalogu kolekcji.');

            return self::FAILURE;
        }

        foreach ($byPrefix as $prefix => $names) {
            $names = array_values($names);
            sort($names);

            try {
                $data = $catalog->subset($prefix, $names);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $path = rtrim($directory, '/') . '/' . $prefix . '.json';
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
            $this->info($path . ': ' . count($names) . ' ikon');
        }

        return self::SUCCESS;
    }
}
