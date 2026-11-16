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

namespace Simplysmart\Polygon\Deploy\Deployment;

/**
 * W konfiguracji wdrożenia brakuje pliku, projektu albo wymaganych kluczy.
 */
final class MissingConfigException extends DeployConfigException
{
    public static function file(string $file): self
    {
        return new self("Brak {$file}.");
    }

    /**
     * @param  string  $name  Nazwa projektu.
     * @param  string  $file  Oczekiwany plik projektu.
     * @param  list<string>  $available  Projekty, które mają konfigurację.
     * @param  string  $example  Wzór konfiguracji.
     */
    public static function project(string $name, string $file, array $available, string $example): self
    {
        return new self("Brak projektu {$name} ({$file}). " . self::hint($available, $example));
    }

    /**
     * @param  list<string>  $available  Projekty, które mają konfigurację.
     * @param  string  $example  Wzór konfiguracji.
     */
    public static function noProject(array $available, string $example): self
    {
        return new self('Podaj projekt. ' . self::hint($available, $example));
    }

    /**
     * @param  list<string>  $keys  Brakujące klucze.
     * @param  string  $example  Wzór konfiguracji.
     */
    public static function keys(string $file, array $keys, string $example): self
    {
        return new self("Brak wymaganych wartości w {$file}: " . implode(', ', $keys) . ". Wzór: {$example}.");
    }

    /**
     * @param  list<string>  $available
     */
    private static function hint(array $available, string $example): string
    {
        return $available === []
            ? "Utwórz plik według {$example}."
            : 'Dostępne projekty: ' . implode(', ', $available) . '.';
    }
}
