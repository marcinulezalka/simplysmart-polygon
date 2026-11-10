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
use Simplysmart\Polygon\Contracts\ZoneRegistry;
use Simplysmart\Polygon\Organizations\DefaultOrganization;
use Simplysmart\Polygon\Permissions\RequiredRole;

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
 * Organizacje (sekcję `organizations` i organizacje kont) czyta InstallOrganizationParser, pojedyncze wartości — InstallValues.
 */
final readonly class InstallFileParser
{
    public const string ALL = '*';

    public const string RANDOM_ID = 'random';

    public function __construct(
        private ZoneRegistry $zones,
        private InstallValues $values,
        private InstallOrganizationParser $organizationParser,
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

        return new InstallFile($this->catalog($path, $data['roles'] ?? []), $result, $this->organizationParser->organizations($path, $data['organizations'] ?? []));
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

            if (preg_match(InstallValues::ROLE_PATTERN, $role) !== 1 || ($permissions !== [] && $permissions !== self::ALL)) {
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

        $permissions = $this->values->names($path, $definition['permissions'] ?? [], "{$zone}.permissions", InstallValues::PERMISSION_PATTERN);
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

            if (preg_match(InstallValues::ROLE_PATTERN, $role) !== 1 || ! is_array($definition)) {
                throw InvalidInstallFile::in($path, "role [{$zone}.{$role}] must be kebab-case with an object definition.");
            }

            $own = ($definition['permissions'] ?? []) === self::ALL
                ? $permissions
                : $this->values->names($path, $definition['permissions'] ?? [], "{$zone}.roles.{$role}.permissions", InstallValues::PERMISSION_PATTERN, $permissions);
            $definitions[$role] = [
                'permissions' => $own,
                'except' => $this->values->names($path, $definition['except'] ?? [], "{$zone}.roles.{$role}.except", InstallValues::PERMISSION_PATTERN, $permissions),
                'inherits' => $this->values->names($path, $definition['inherits'] ?? [], "{$zone}.roles.{$role}.inherits", InstallValues::ROLE_PATTERN),
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
            $organizationRoles = $this->organizationParser->accountOrganizations($path, $account['organizations'] ?? [], "{$where}.organizations");
            $result[] = new InstallAccount(
                $email,
                $name,
                $this->values->names($path, $account['roles'] ?? [], "{$where}.roles", InstallValues::ROLE_PATTERN),
                $id === self::RANDOM_ID,
                array_keys($organizationRoles),
                array_filter($organizationRoles, static fn(array $roles): bool => $roles !== []),
                $this->values->attributes($path, $account, 'attributes', $where),
                $this->defaultOrganization($path, $account['defaults'] ?? [], array_keys($organizationRoles), "{$where}.defaults"),
            );
        }

        return $result;
    }

    /**
     * Klucz domyślnej organizacji konta z `defaults` (obiekt; dziś tylko `organization` — klucz jednej z organizacji
     * konta) albo null; inne pola, nieznany albo obcy klucz to błąd.
     *
     * @param list<string> $organizations Klucze organizacji konta z `organizations`.
     *
     * @throws InvalidInstallFile
     */
    private function defaultOrganization(string $path, mixed $defaults, array $organizations, string $where): ?string
    {
        if (! is_array($defaults) || ($defaults !== [] && array_is_list($defaults))) {
            throw InvalidInstallFile::in($path, "{$where} must be an object.");
        }

        $unknown = array_diff(array_map(strval(...), array_keys($defaults)), [DefaultOrganization::SETTING]);

        if ($unknown !== []) {
            throw InvalidInstallFile::in($path, "{$where} has unknown fields: " . implode(', ', $unknown) . '.');
        }

        $organization = $defaults[DefaultOrganization::SETTING] ?? null;

        if ($organization !== null && (! is_string($organization) || ! in_array($organization, $organizations, true))) {
            throw InvalidInstallFile::in($path, "{$where}." . DefaultOrganization::SETTING . ' must be the key of one of the account organizations.');
        }

        return $organization;
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
