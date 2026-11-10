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
use Illuminate\Process\Factory as Process;
use RuntimeException;
use Simplysmart\Polygon\Icons\IconCatalog;
use Simplysmart\Polygon\Icons\IconifyIcon;

/**
 * Narzędzie deweloperskie (docs/ikony.md): ikona do e-maila jako PNG — klienty poczty nie wyświetlają SVG ani web
 * componentów. Pobiera SVG z API Iconify w kolorze i rozmiarze (Icons\IconCatalog::svg()) i rasteryzuje je programem
 * `rsvg-convert` (librsvg, zalecany) albo `inkscape` w podwójnej rozdzielczości (--scale, ostre na ekranach o dużej
 * gęstości); tymczasowy SVG leży obok pliku wyjściowego (Inkscape ze snapa nie widzi systemowego /tmp). PNG trafia
 * do repozytorium paczki, a mail osadza go (Message::embed()) i wyświetla w rozmiarze --size. Kolor jest stały — klient
 * poczty go nie zmieni.
 */
final class IconPngCommand extends Command
{
    protected $signature = 'polygon:icons:png {icon : Ikona „zestaw:ikona”} {output : Plik PNG} {--size=24 : Rozmiar w mailu (px)} {--scale=2 : Gęstość pikseli} {--color=#1d4873 : Kolor ikony}';

    protected $description = 'Zapisuje ikonę Iconify jako PNG do e-maili';

    public function handle(IconCatalog $catalog, Process $process): int
    {
        $icon = IconifyIcon::tryFrom($this->inputText('icon'));
        $output = $this->inputText('output');
        $pixels = max(1, (int) $this->inputText('size', true)) * max(1, (int) $this->inputText('scale', true));
        $color = $this->inputText('color', true);

        if ($icon === null || preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            $this->error('Podaj ikonę „zestaw:ikona” i kolor #rrggbb.');

            return self::FAILURE;
        }

        try {
            $svg = $catalog->svg($icon, $color, $pixels);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $source = dirname($output) . '/.' . pathinfo($output, PATHINFO_FILENAME) . '-' . bin2hex(random_bytes(4)) . '.svg';

        if (! is_dir(dirname($output)) || file_put_contents($source, $svg) === false) {
            $this->error('Nie można zapisać pliku w katalogu ' . dirname($output) . '.');

            return self::FAILURE;
        }

        try {
            $converters = [
                'rsvg-convert' => ['rsvg-convert', '--width', (string) $pixels, '--height', (string) $pixels, '--output', $output, $source],
                'inkscape' => ['inkscape', '--export-type=png', '--export-filename=' . $output, '--export-width=' . $pixels, '--export-height=' . $pixels, $source],
            ];

            foreach ($converters as $name => $command) {
                if (! $process->newPendingProcess()->run(['sh', '-c', 'command -v ' . $name])->successful()) {
                    continue;
                }

                $result = $process->newPendingProcess()->run($command);

                if (! $result->successful() || ! is_file($output)) {
                    $this->error(trim($result->errorOutput()) ?: "{$name} nie zapisał pliku PNG.");

                    return self::FAILURE;
                }

                $this->info("{$output}: {$icon->value()} {$pixels}×{$pixels} px ({$name})");

                return self::SUCCESS;
            }
        } finally {
            unlink($source);
        }

        $this->error('Brak programu do rasteryzacji SVG: zainstaluj rsvg-convert (librsvg) albo inkscape.');

        return self::FAILURE;
    }

    /**
     * Argument albo opcja komendy jako tekst (pusty, gdy wartość nie jest skalarem).
     */
    private function inputText(string $name, bool $option = false): string
    {
        $value = $option ? $this->option($name) : $this->argument($name);

        return is_scalar($value) ? (string) $value : '';
    }
}
