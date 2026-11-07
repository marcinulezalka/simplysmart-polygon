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

namespace Simplysmart\Polygon\Install;

/**
 * Pojedyncze wartości pliku instalacji (docs/instalacja.md): listy nazw zgodnych ze wzorcem, teksty, odwołania i flagi.
 * Błędna wartość to InvalidInstallFile z miejscem w pliku ($where, np. `polygon.accounts[0].roles`).
 */
final readonly class InstallValues
{
    /** Nazwa uprawnienia `zasób.akcja` w kebab-case. */
    public const string PERMISSION_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*\.[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /** Nazwa roli w kebab-case. */
    public const string ROLE_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /**
     * Lista nazw zgodnych z $pattern, bez powtórzeń.
     *
     * @param list<string>|null $allowed Dozwolone wartości (np. uprawnienia strefy); null — tylko wzorzec.
     *
     * @return list<string>
     *
     * @throws InvalidInstallFile
     */
    public function names(string $path, mixed $names, string $where, string $pattern, ?array $allowed = null): array
    {
        if (! is_array($names) || ! array_is_list($names)) {
            throw InvalidInstallFile::in($path, "{$where} must be a list.");
        }

        foreach ($names as $name) {
            if (! is_string($name) || preg_match($pattern, $name) !== 1) {
                throw InvalidInstallFile::in($path, "{$where} has an invalid name.");
            }

            if ($allowed !== null && ! in_array($name, $allowed, true)) {
                throw InvalidInstallFile::in($path, "{$where} refers to [{$name}], which is not defined.");
            }
        }

        /** @var list<string> $names */
        return array_values(array_unique($names));
    }

    /**
     * Opcjonalny niepusty tekst do $length znaków.
     *
     * @param array<array-key, mixed> $definition
     *
     * @throws InvalidInstallFile
     */
    public function text(string $path, array $definition, string $field, string $where, int $length): ?string
    {
        if (! array_key_exists($field, $definition)) {
            return null;
        }

        $value = $definition[$field];

        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $length) {
            throw InvalidInstallFile::in($path, "{$where}.{$field} must be a non-empty text of at most {$length} characters.");
        }

        return trim($value);
    }

    /**
     * Opcjonalne odwołanie (klucz organizacji, kod typu) zgodne z $pattern.
     *
     * @param array<array-key, mixed> $definition
     *
     * @throws InvalidInstallFile
     */
    public function reference(string $path, array $definition, string $field, string $where, string $pattern): ?string
    {
        $value = $definition[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || preg_match($pattern, $value) !== 1) {
            throw InvalidInstallFile::in($path, "{$where}.{$field} is not a valid reference.");
        }

        return $value;
    }

    /**
     * Opcjonalna flaga (domyślnie false).
     *
     * @param array<array-key, mixed> $definition
     *
     * @throws InvalidInstallFile
     */
    public function flag(string $path, array $definition, string $field, string $where): bool
    {
        $value = $definition[$field] ?? false;

        return is_bool($value) ? $value : throw InvalidInstallFile::in($path, "{$where}.{$field} must be true or false.");
    }
}
