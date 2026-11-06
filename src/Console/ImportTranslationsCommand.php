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
use JsonException;
use Simplysmart\Polygon\Translation\TranslationImport;

/**
 * Import wypełnionego szablonu tłumaczeń (TranslationImport): `polygon:translations:import {file}` zapisuje
 * tłumaczenia do nadpisań projektu `lang/vendor/<przestrzeń>/<język>/<plik>.php` (tylko klucze szablonu, niepuste).
 */
final class ImportTranslationsCommand extends Command
{
    protected $signature = 'polygon:translations:import {file : Plik JSON z szablonu polygon:translations:export}';

    protected $description = 'Importuje tłumaczenia z pliku JSON do lang/vendor projektu';

    public function handle(TranslationImport $import): int
    {
        $path = $this->argument('file');
        $path = is_string($path) ? $path : '';
        $json = is_file($path) ? file_get_contents($path) : false;

        if ($json === false || filesize($path) > TranslationImport::MAX_KILOBYTES * 1024) {
            $this->error("Nie można odczytać pliku {$path} (najwyżej " . TranslationImport::MAX_KILOBYTES . ' KB).');

            return self::FAILURE;
        }

        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('Nieprawidłowy JSON: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $language = is_array($data) ? ($data['language'] ?? null) : null;
        $source = is_array($data) ? ($data['source'] ?? null) : null;
        $texts = is_array($data) ? ($data['texts'] ?? null) : null;

        if (! is_string($language) || $language === '' || ! is_string($source) || $source === '' || ! is_array($texts)) {
            $this->error('Plik musi mieć pola "language", "source" i "texts".');

            return self::FAILURE;
        }

        $count = $import->import($language, $source, $texts);
        $this->info("Zapisano tłumaczeń: {$count} (język {$language}).");

        return self::SUCCESS;
    }
}
