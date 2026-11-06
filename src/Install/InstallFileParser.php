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
use Simplysmart\Polygon\Permissions\RequiredRole;
use Simplysmart\Polygon\Zones\PolygonZone;

/**
 * Czyta i waliduje plik instalacji, zanim cokolwiek trafi do bazy (docs/instalacja.md):
 * ```json
 * {"zones": {"polygon": {
 *     "permissions": ["settings.view", "settings.manage"],
 *     "roles": {"guest": {"permissions": []}, "support": {"permissions": ["settings.view"]},
 *               "owner": {"inherits": ["support"], "permissions": ["settings.manage"]}, "hero": {"permissions": "*"}},
 *     "accounts": [{"email": "support@example.com", "name": "Support", "roles": ["hero"], "id": "random"}]}}}
 * ```
 * Sprawdza: znane strefy (SUPPORTED_ZONES), rolę root `hero` tylko jako `"*"` bez `except` (role obowiązkowe
 * i pełne uprawnienia roota sprawdza Installer po połączeniu plików), nazwy uprawnień `zasób.akcja` i ról w kebab-case, uprawnienia ról
 * i `except` z listy `permissions`, `inherits` do istniejących ról bez cykli, konta z poprawnym adresem, unikalnym
 * w pliku (istnienie ich ról sprawdza Installer po połączeniu plików); `id` konta to `"random"` (losowy slot 1–999) albo brak (id od 1000).
 */
final readonly class InstallFileParser
{
    public const string ALL = '*';

    public const string RANDOM_ID = 'random';

    /**
     * Strefy, których konta i guard zna paczka.
     *
     * @var list<string>
     */
    public const array SUPPORTED_ZONES = [PolygonZone::NAME];

    private const string PERMISSION_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*\.[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    private const string ROLE_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /**
     * @return list<ZoneInstall>
     *
     * @throws InvalidInstallFile
     */
    public function parse(string $path): array
    {
        $data = $this->decode($path);
        $zones = $data['zones'] ?? null;

        if (! is_array($zones) || $zones === [] || array_is_list($zones)) {
            throw InvalidInstallFile::in($path, '"zones" must be an object of zone names.');
        }

        $result = [];

        foreach ($zones as $zone => $definition) {
            $result[] = $this->zone($path, (string) $zone, $definition);
        }

        return $result;
    }

    private function zone(string $path, string $zone, mixed $definition): ZoneInstall
    {
        if (! in_array($zone, self::SUPPORTED_ZONES, true)) {
            throw InvalidInstallFile::in($path, "zone [{$zone}] is not supported (" . implode(', ', self::SUPPORTED_ZONES) . ').');
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
            $result[] = new InstallAccount($email, $name, $this->names($path, $account['roles'] ?? [], "{$where}.roles", self::ROLE_PATTERN), $id === self::RANDOM_ID);
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
