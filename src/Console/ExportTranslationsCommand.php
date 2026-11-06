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
use Illuminate\Config\Repository;
use Simplysmart\Polygon\Translation\TranslationTemplate;

/**
 * Szablon tłumaczeń JSON (TranslationTemplate): `polygon:translations:export {language} [--source=] [--blank] [--output=]`
 * — wszystkie teksty paczek w języku źródłowym z istniejącym tłumaczeniem; `--blank` dla nowego języka. Wypełniony
 * plik wraca przez `polygon:translations:import`.
 */
final class ExportTranslationsCommand extends Command
{
    protected $signature = 'polygon:translations:export
        {language : Kod języka docelowego (np. en)}
        {--source= : Język źródłowy (domyślnie APP_LOCALE)}
        {--blank : Pusty szablon nowego języka}
        {--output= : Plik wynikowy (domyślnie standardowe wyjście)}';

    protected $description = 'Eksportuje szablon tłumaczeń paczek do JSON';

    public function handle(TranslationTemplate $template, Repository $config): int
    {
        $language = $this->stringArgument('language');
        $source = $this->option('source');
        $source = is_string($source) && $source !== '' ? $source : $config->string('app.locale');
        $data = $this->option('blank') === true ? $template->blank($source) : $template->for($language, $source);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $output = $this->option('output');

        if (! is_string($output) || $output === '') {
            $this->line($json);

            return self::SUCCESS;
        }

        file_put_contents($output, $json . "\n");
        $counts = $template->counts($language, $source);
        $this->info("Zapisano {$output}: {$counts['translated']}/{$counts['all']} fraz ({$counts['percent']}%).");

        return self::SUCCESS;
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? trim($value) : '';
    }
}
