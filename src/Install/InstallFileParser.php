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

use JsonException;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Permissions\RequiredRole;
use Simplysmart\Polygon\Contracts\ZoneRegistry;

/**
 * Czyta i waliduje plik instalacji, zanim cokolwiek trafi do bazy (docs/instalacja.md):
 * ```json
 * {"roles": {"guest": {"permissions": []}, "manager": {"permissions": []}, "hero": {"permissions": "*"}},
 *  "zones": {"polygon": {
 *     "permissions": ["settings.view", "settings.manage"],
 *     "roles": {"guest": {"permissions": []}, "support": {"permissions": ["settings.view"]},
 *               "owner": {"inherits": ["support"], "permissions": ["settings.manage"]}, "hero": {"permissions": "*"}},
 *     "accounts": [{"email": "support@example.com", "name": "Support", "roles": ["hero"], "id": "random"}]}}}
 * ```
 * Katalog `roles` to role każdej zarejestrowanej strefy z kontami, z uprawnieniami `[]` albo `"*"` (wszystkie uprawnienia
 * strefy); strefa ustawia im własne w `zones.<strefa>.roles`. Plik ma co najmniej jedną z sekcji.
 * Sprawdza: strefy zarejestrowane z kontami (Contracts\ZoneRegistry), rolę root `hero` tylko jako `"*"` bez `except` (role obowiązkowe
 * i pełne uprawnienia roota sprawdza Installer po połączeniu plików), nazwy uprawnień `zasób.akcja` i ról w kebab-case, uprawnienia ról
 * i `except` z listy `permissions`, `inherits` do istniejących ról bez cykli, konta z poprawnym adresem, unikalnym
 * w pliku (istnienie ich ról sprawdza Installer po połączeniu plików); `id` konta to `"random"` (losowy slot 1–999) albo brak (id od 1000).
 */
final readonly class InstallFileParser
{
    public const string ALL = '*';

    public const string RANDOM_ID = 'random';

    private const string PERMISSION_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*\.[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    private const string ROLE_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /** Klucz organizacji w plikach instalacji (kebab-case, jak role). */
    private const string KEY_PATTERN = self::ROLE_PATTERN;

    /** Kod typu jednostki (`organization_unit_types.code`, np. `placowka`). */
    private const string UNIT_TYPE_PATTERN = '/^[a-z][a-z0-9_]*$/';

    private const string PARENT = 'parent';

    private const string UNIT_TYPE = 'unit_type';

    private const int NAME_MAX_LENGTH = 255;

    /** Pola organizacji poza danymi firmy. */
    private const array ORGANIZATION_FIELDS = [ColumnNames::NAME, Organization::SHORT_NAME, self::PARENT, self::UNIT_TYPE, Organization::IS_DEPARTMENT];

    public function __construct(
        private ZoneRegistry $zones,
    ) {}

    /**
     * @throws InvalidInstallFile
     */
    public function parse(string $path): InstallFile
    {
        $data = $this->decode($path);

        if (! array_key_exists('zones', $data) && ! array_key_exists('roles', $data) && ! array_key_exists('organizations', $data)) {
            throw InvalidInstallFile::in($path, 'the file needs "roles", "zones" or "organizations".');
        }

        $zones = $data['zones'] ?? [];

        if (! is_array($zones) || ($zones !== [] && array_is_list($zones))) {
            throw InvalidInstallFile::in($path, '"zones" must be an object of zone names.');
        }

        $result = [];

        foreach ($zones as $zone => $definition) {
            $result[] = $this->zone($path, (string) $zone, $definition);
        }

        return new InstallFile($this->catalog($path, $data['roles'] ?? []), $result, $this->organizations($path, $data['organizations'] ?? []));
    }

    /**
     * Organizacje: klucz => `name` (wymagana), opcjonalnie `short_name`, `parent` (klucz), `unit_type` (kod typu
     * jednostki), `is_department` i dane firmy (Organization::DETAIL_LENGTHS); inne pola to błąd.
     *
     * @return list<InstallOrganization>
     */
    private function organizations(string $path, mixed $organizations): array
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
                $this->text($path, $definition, ColumnNames::NAME, $where, self::NAME_MAX_LENGTH) ?? throw InvalidInstallFile::in($path, "{$where} needs a \"name\"."),
                $this->text($path, $definition, Organization::SHORT_NAME, $where, Organization::SHORT_NAME_MAX_LENGTH),
                $this->reference($path, $definition, self::PARENT, $where, self::KEY_PATTERN),
                $this->reference($path, $definition, self::UNIT_TYPE, $where, self::UNIT_TYPE_PATTERN),
                $this->flag($path, $definition, Organization::IS_DEPARTMENT, $where),
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
     */
    private function accountOrganizations(string $path, mixed $organizations, string $where): array
    {
        if (is_array($organizations) && $organizations !== [] && ! array_is_list($organizations)) {
            $result = [];

            foreach ($organizations as $key => $roles) {
                $result[$this->names($path, [(string) $key], $where, self::KEY_PATTERN)[0]] = $this->names($path, $roles, "{$where}.{$key}", self::ROLE_PATTERN);
            }
        } else {
            $result = array_fill_keys($this->names($path, $organizations, $where, self::KEY_PATTERN), []);
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
            $value = $this->text($path, $definition, $column, $where, $length);

            if ($value !== null) {
                $details[$column] = $value;
            }
        }

        return $details;
    }

    /**
     * Opcjonalny niepusty tekst do $length znaków.
     *
     * @param array<array-key, mixed> $definition
     */
    private function text(string $path, array $definition, string $field, string $where, int $length): ?string
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
     */
    private function reference(string $path, array $definition, string $field, string $where, string $pattern): ?string
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
     * @param array<array-key, mixed> $definition
     */
    private function flag(string $path, array $definition, string $field, string $where): bool
    {
        $value = $definition[$field] ?? false;

        return is_bool($value) ? $value : throw InvalidInstallFile::in($path, "{$where}.{$field} must be true or false.");
    }

    /**
     * Katalog ról: rola => czy ma wszystkie uprawnienia strefy (`"*"`); `[]` — bez uprawnień. Rola root tylko z `"*"`.
     *
     * @return array<string, bool>
     */
    private function catalog(string $path, mixed $roles): array
    {
        if ($roles === []) {
            return [];
        }

        if (! is_array($roles) || array_is_list($roles)) {
            throw InvalidInstallFile::in($path, '"roles" must be an object of role names.');
        }

        $catalog = [];

        foreach ($roles as $role => $definition) {
            $role = (string) $role;
            $permissions = is_array($definition) && count($definition) === 1 && array_key_exists('permissions', $definition) ? $definition['permissions'] : null;

            if (preg_match(self::ROLE_PATTERN, $role) !== 1 || ($permissions !== [] && $permissions !== self::ALL)) {
                throw InvalidInstallFile::in($path, "catalog role [{$role}] must be kebab-case with \"permissions\": [] or \"*\" only.");
            }

            if ($role === RequiredRole::ROOT->value && $permissions !== self::ALL) {
                throw InvalidInstallFile::in($path, "catalog role [{$role}] is the root role: only \"permissions\": \"*\" is allowed.");
            }

            $catalog[$role] = $permissions === self::ALL;
        }

        return $catalog;
    }

    private function zone(string $path, string $zone, mixed $definition): ZoneInstall
    {
        if (! $this->zones->has($zone) || $this->zones->get($zone)->model === null) {
            throw InvalidInstallFile::in($path, "zone [{$zone}] is not a registered zone with accounts.");
        }

        if (! is_array($definition)) {
            throw InvalidInstallFile::in($path, "zone [{$zone}] must be an object.");
        }

        $permissions = $this->names($path, $definition['permissions'] ?? [], "{$zone}.permissions", self::PERMISSION_PATTERN);
        $roles = $this->roles($path, $zone, $definition['roles'] ?? [], $permissions);
        $this->rootRole($path, $zone, $definition['roles'] ?? []);
        $accounts = $this->accounts($path, $zone, $definition['accounts'] ?? []);

        return new ZoneInstall($zone, $permissions, $roles, $accounts);
    }

    /**
     * Rola root (`hero`), gdy plik ją definiuje: tylko `"*"` bez `except` — Installer nadaje jej wszystkie uprawnienia
     * strefy ze wszystkich plików.
     */
    private function rootRole(string $path, string $zone, mixed $definitions): void
    {
        $root = RequiredRole::ROOT->value;
        $definition = is_array($definitions) ? ($definitions[$root] ?? null) : null;

        if ($definition !== null && (! is_array($definition) || ($definition['permissions'] ?? null) !== self::ALL || ($definition['except'] ?? []) !== [] || ($definition['inherits'] ?? []) !== [])) {
            throw InvalidInstallFile::in($path, "role [{$zone}.{$root}] is the root role: only \"permissions\": \"*\" is allowed.");
        }
    }

    /**
     * @param list<string> $permissions
     *
     * @return array<string, list<string>>
     */
    private function roles(string $path, string $zone, mixed $roles, array $permissions): array
    {
        if ($roles === []) {
            return [];
        }

        if (! is_array($roles) || array_is_list($roles)) {
            throw InvalidInstallFile::in($path, "{$zone}.roles must be an object of role names.");
        }

        $definitions = [];

        foreach ($roles as $role => $definition) {
            $role = (string) $role;

            if (preg_match(self::ROLE_PATTERN, $role) !== 1 || ! is_array($definition)) {
                throw InvalidInstallFile::in($path, "role [{$zone}.{$role}] must be kebab-case with an object definition.");
            }

            $own = ($definition['permissions'] ?? []) === self::ALL
                ? $permissions
                : $this->names($path, $definition['permissions'] ?? [], "{$zone}.roles.{$role}.permissions", self::PERMISSION_PATTERN, $permissions);
            $definitions[$role] = [
                'permissions' => $own,
                'except' => $this->names($path, $definition['except'] ?? [], "{$zone}.roles.{$role}.except", self::PERMISSION_PATTERN, $permissions),
                'inherits' => $this->names($path, $definition['inherits'] ?? [], "{$zone}.roles.{$role}.inherits", self::ROLE_PATTERN),
            ];
        }

        $flattened = [];

        foreach (array_keys($definitions) as $role) {
            $flattened[$role] = $this->flatten($path, $zone, $role, $definitions, []);
        }

        return $flattened;
    }

    /**
     * @param array<string, array{permissions: list<string>, except: list<string>, inherits: list<string>}> $definitions
     * @param list<string> $trail
     *
     * @return list<string>
     */
    private function flatten(string $path, string $zone, string $role, array $definitions, array $trail): array
    {
        if (in_array($role, $trail, true)) {
            throw InvalidInstallFile::in($path, "roles of [{$zone}] inherit in a cycle: " . implode(' → ', [...$trail, $role]) . '.');
        }

        $definition = $definitions[$role] ?? throw InvalidInstallFile::in($path, "role [{$zone}.{$role}] is inherited but not defined.");
        $permissions = $definition['permissions'];

        foreach ($definition['inherits'] as $parent) {
            $permissions = [...$permissions, ...$this->flatten($path, $zone, $parent, $definitions, [...$trail, $role])];
        }

        return array_values(array_diff(array_values(array_unique($permissions)), $definition['except']));
    }

    /**
     * @return list<InstallAccount>
     */
    private function accounts(string $path, string $zone, mixed $accounts): array
    {
        if (! is_array($accounts) || ! array_is_list($accounts)) {
            throw InvalidInstallFile::in($path, "{$zone}.accounts must be a list.");
        }

        $result = [];
        $emails = [];

        foreach ($accounts as $index => $account) {
            $where = "{$zone}.accounts[{$index}]";
            $email = is_array($account) && is_string($account['email'] ?? null) ? mb_strtolower(trim($account['email'])) : '';
            $name = is_array($account) && is_string($account['name'] ?? null) ? trim($account['name']) : '';

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $name === '') {
                throw InvalidInstallFile::in($path, "{$where} needs a valid \"email\" and a \"name\".");
            }

            if (in_array($email, $emails, true)) {
                throw InvalidInstallFile::in($path, "{$where} repeats the address [{$email}].");
            }

            $id = $account['id'] ?? null;

            if ($id !== null && $id !== self::RANDOM_ID) {
                throw InvalidInstallFile::in($path, "{$where}.id must be \"" . self::RANDOM_ID . '" or absent.');
            }

            $emails[] = $email;
            $organizationRoles = $this->accountOrganizations($path, $account['organizations'] ?? [], "{$where}.organizations");
            $result[] = new InstallAccount(
                $email,
                $name,
                $this->names($path, $account['roles'] ?? [], "{$where}.roles", self::ROLE_PATTERN),
                $id === self::RANDOM_ID,
                array_keys($organizationRoles),
                array_filter($organizationRoles, static fn(array $roles): bool => $roles !== []),
            );
        }

        return $result;
    }

    /**
     * @param list<string>|null $allowed Dozwolone wartości (np. uprawnienia strefy); null — tylko wzorzec.
     *
     * @return list<string>
     */
    private function names(string $path, mixed $names, string $where, string $pattern, ?array $allowed = null): array
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
     * @return array<array-key, mixed>
     */
    private function decode(string $path): array
    {
        $json = is_file($path) ? file_get_contents($path) : false;

        if ($json === false) {
            throw InvalidInstallFile::in($path, 'the file cannot be read.');
        }

        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidInstallFile::in($path, 'invalid JSON: ' . $exception->getMessage());
        }

        return is_array($data) ? $data : throw InvalidInstallFile::in($path, 'the root must be an object.');
    }
}
