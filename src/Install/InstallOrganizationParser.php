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

use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;

/**
 * Organizacje w pliku instalacji (docs/instalacja.md): sekcja `organizations` (klucz => `name`, opcjonalnie
 * `short_name`, `parent`, `unit_type`, `is_department` i dane firmy) oraz `organizations` kont stref (organizacje konta
 * z jego rolami w każdej z nich). Istnienie organizacji nadrzędnych i organizacji kont sprawdza InstallOrganizations
 * po połączeniu plików.
 */
final readonly class InstallOrganizationParser
{
    /** Klucz organizacji w plikach instalacji (kebab-case, jak role). */
    private const string KEY_PATTERN = InstallValues::ROLE_PATTERN;

    /** Kod typu jednostki (`organization_unit_types.code`, np. `placowka`). */
    private const string UNIT_TYPE_PATTERN = '/^[a-z][a-z0-9_]*$/';

    private const string PARENT = 'parent';

    private const string UNIT_TYPE = 'unit_type';

    private const int NAME_MAX_LENGTH = 255;

    /** Pola organizacji poza danymi firmy. */
    private const array ORGANIZATION_FIELDS = [ColumnNames::NAME, Organization::SHORT_NAME, self::PARENT, self::UNIT_TYPE, Organization::IS_DEPARTMENT];

    public function __construct(
        private InstallValues $values,
    ) {}

    /**
     * Organizacje: klucz => `name` (wymagana), opcjonalnie `short_name`, `parent` (klucz), `unit_type` (kod typu
     * jednostki), `is_department` i dane firmy (Organization::DETAIL_LENGTHS); inne pola to błąd.
     *
     * @return list<InstallOrganization>
     *
     * @throws InvalidInstallFile
     */
    public function organizations(string $path, mixed $organizations): array
    {
        if (! is_array($organizations) || ($organizations !== [] && array_is_list($organizations))) {
            throw InvalidInstallFile::in($path, '"organizations" must be an object of organization keys.');
        }

        $result = [];

        foreach ($organizations as $key => $definition) {
            $key = (string) $key;
            $where = "organizations.{$key}";

            if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                throw InvalidInstallFile::in($path, "{$where}: the key must be kebab-case.");
            }

            if ($key === Organization::HERO_KEY) {
                throw InvalidInstallFile::in($path, "{$where}: the key [" . Organization::HERO_KEY . '] is reserved for the hero organization (use it only as "parent").');
            }

            if (! is_array($definition) || array_is_list($definition)) {
                throw InvalidInstallFile::in($path, "{$where} must be an object.");
            }

            $unknown = array_diff(array_keys($definition), [...self::ORGANIZATION_FIELDS, ...array_keys(Organization::DETAIL_LENGTHS)]);

            if ($unknown !== []) {
                throw InvalidInstallFile::in($path, "{$where} has unknown fields: " . implode(', ', $unknown) . '.');
            }

            $result[] = new InstallOrganization(
                $key,
                $this->values->text($path, $definition, ColumnNames::NAME, $where, self::NAME_MAX_LENGTH) ?? throw InvalidInstallFile::in($path, "{$where} needs a \"name\"."),
                $this->values->text($path, $definition, Organization::SHORT_NAME, $where, Organization::SHORT_NAME_MAX_LENGTH),
                $this->values->reference($path, $definition, self::PARENT, $where, self::KEY_PATTERN),
                $this->values->reference($path, $definition, self::UNIT_TYPE, $where, self::UNIT_TYPE_PATTERN),
                $this->values->flag($path, $definition, Organization::IS_DEPARTMENT, $where),
                $this->details($path, $definition, $where),
            );
        }

        return $result;
    }

    /**
     * Organizacje konta z rolami w każdej z nich: lista kluczy (`["clinic"]`, bez ról organizacji) albo obiekt klucz =>
     * lista ról (`{"clinic": ["owner"]}`). Organizacji hero nie można przypisać (jej członkiem zostaje tylko konto
     * z rolą hero).
     *
     * @return array<string, list<string>> Klucz organizacji => role konta tylko w niej (w kolejności z pliku).
     *
     * @throws InvalidInstallFile
     */
    public function accountOrganizations(string $path, mixed $organizations, string $where): array
    {
        if (is_array($organizations) && $organizations !== [] && ! array_is_list($organizations)) {
            $result = [];

            foreach ($organizations as $key => $roles) {
                $result[$this->values->names($path, [(string) $key], $where, self::KEY_PATTERN)[0]] = $this->values->names($path, $roles, "{$where}.{$key}", InstallValues::ROLE_PATTERN);
            }
        } else {
            $result = array_fill_keys($this->values->names($path, $organizations, $where, self::KEY_PATTERN), []);
        }

        if (array_key_exists(Organization::HERO_KEY, $result)) {
            throw InvalidInstallFile::in($path, "{$where}: the hero organization comes only with the hero role.");
        }

        return $result;
    }

    /**
     * Dane firmy organizacji: kolumna => wartość (tylko podane).
     *
     * @param array<array-key, mixed> $definition
     *
     * @return array<string, string>
     */
    private function details(string $path, array $definition, string $where): array
    {
        $details = [];

        foreach (Organization::DETAIL_LENGTHS as $column => $length) {
            $value = $this->values->text($path, $definition, $column, $where, $length);

            if ($value !== null) {
                $details[$column] = $value;
            }
        }

        return $details;
    }
}
