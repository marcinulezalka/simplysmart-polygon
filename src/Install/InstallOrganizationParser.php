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

use Simplysmart\Polygon\Calendar\WeekCycle;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Enums\LeaseAccess;
use Simplysmart\Polygon\Enums\LeaseMode;
use Simplysmart\Polygon\Enums\ResourceKind;
use Simplysmart\Polygon\Models\Organization;
use Simplysmart\Polygon\Models\OrganizationResource;

/**
 * Organizacje w pliku instalacji (docs/instalacja.md): sekcja `organizations` (klucz => `name`, opcjonalnie
 * `short_name`, `parent`, `unit_type`, `is_department`, `is_cooperation`, dane firmy, `resources` — zasoby organizacji,
 * `schedule` — grafik i `leases` — umowy najmu jej zasobów) oraz `organizations` kont stref (organizacje konta
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

    private const string RESOURCES = 'resources';

    private const string SCHEDULE = 'schedule';

    private const string LEASES = 'leases';

    private const string ATTRIBUTES = 'attributes';

    /** Pola umowy najmu. */
    private const array LEASE_FIELDS = ['resource', 'lessee', 'cooperation', 'number', 'signed', 'from', 'to', 'windows', 'access', self::EVERY_WEEKS, 'mode'];

    /** Pola okna umowy najmu. */
    private const array WINDOW_FIELDS = ['days', 'from', 'to'];

    /** Pola wpisu grafiku organizacji. */
    private const array SCHEDULE_FIELDS = ['resource', 'performer', 'weekday', 'from', 'to', 'slot', 'valid_from', 'valid_to', self::EVERY_WEEKS, 'fills_gaps'];

    /** Co ile tygodni (grafik i umowa najmu), domyślnie 1. */
    private const string EVERY_WEEKS = 'every_weeks';

    private const string TIME_PATTERN = '/^([01]\\d|2[0-3]):[0-5]\\d$/';

    private const string DATE_PATTERN = '/^\\d{4}-\\d{2}-\\d{2}$/';

    private const string KEY = 'key';

    /** Pola zasobu organizacji. */
    private const array RESOURCE_FIELDS = [self::KEY, ColumnNames::NAME, OrganizationResource::KIND, OrganizationResource::CODE];

    private const int NAME_MAX_LENGTH = 255;

    /** Pola organizacji poza danymi firmy. */
    private const array ORGANIZATION_FIELDS = [ColumnNames::NAME, Organization::SHORT_NAME, self::PARENT, self::UNIT_TYPE, Organization::IS_DEPARTMENT, Organization::IS_COOPERATION, self::RESOURCES, self::SCHEDULE, self::LEASES, self::ATTRIBUTES];

    public function __construct(
        private InstallValues $values,
    ) {}

    /**
     * Organizacje: klucz => `name` (wymagana), opcjonalnie `short_name`, `parent` (klucz), `unit_type` (kod typu
     * jednostki), `is_department`, `is_cooperation`, dane firmy (Organization::DETAIL_LENGTHS), `resources`, `schedule`
     * `leases` i `attributes` (InstallValues::attributes()); inne pola to błąd.
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
                $this->resources($path, $definition[self::RESOURCES] ?? [], "{$where}." . self::RESOURCES),
                $this->schedule($path, $definition[self::SCHEDULE] ?? [], "{$where}." . self::SCHEDULE),
                $this->values->flag($path, $definition, Organization::IS_COOPERATION, $where),
                $this->leases($path, $definition[self::LEASES] ?? [], "{$where}." . self::LEASES),
                $this->values->attributes($path, $definition, self::ATTRIBUTES, $where),
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
     * Zasoby organizacji: lista obiektów `key` (kebab-case, unikalny w organizacji), `name` (wymagana), `kind`
     * (Enums\ResourceKind, domyślnie `room`) i opcjonalnie `code` (do OrganizationResource::CODE_MAX_LENGTH znaków,
     * unikalny w organizacji); inne pola to błąd.
     *
     * @return list<InstallResource>
     *
     * @throws InvalidInstallFile
     */
    private function resources(string $path, mixed $resources, string $where): array
    {
        if (! is_array($resources) || ! array_is_list($resources)) {
            throw InvalidInstallFile::in($path, "{$where} must be a list.");
        }

        $result = [];
        $keys = [];
        $codes = [];

        foreach ($resources as $index => $definition) {
            $at = "{$where}[{$index}]";

            if (! is_array($definition) || array_is_list($definition)) {
                throw InvalidInstallFile::in($path, "{$at} must be an object.");
            }

            $unknown = array_diff(array_keys($definition), self::RESOURCE_FIELDS);

            if ($unknown !== []) {
                throw InvalidInstallFile::in($path, "{$at} has unknown fields: " . implode(', ', $unknown) . '.');
            }

            $key = $this->values->reference($path, $definition, self::KEY, $at, self::KEY_PATTERN) ?? throw InvalidInstallFile::in($path, "{$at} needs a \"key\".");
            $kindValue = $definition[OrganizationResource::KIND] ?? ResourceKind::Room->value;
            $kind = (is_string($kindValue) ? ResourceKind::tryFrom($kindValue) : null)
                ?? throw InvalidInstallFile::in($path, "{$at}.kind must be one of: " . implode(', ', array_column(ResourceKind::cases(), 'value')) . '.');
            $code = $this->values->text($path, $definition, OrganizationResource::CODE, $at, OrganizationResource::CODE_MAX_LENGTH);

            if (in_array($key, $keys, true) || ($code !== null && in_array($code, $codes, true))) {
                throw InvalidInstallFile::in($path, "{$at} repeats the key or code of another resource of the organization.");
            }

            $keys[] = $key;
            $codes[] = $code;
            $result[] = new InstallResource(
                $key,
                $this->values->text($path, $definition, ColumnNames::NAME, $at, self::NAME_MAX_LENGTH) ?? throw InvalidInstallFile::in($path, "{$at} needs a \"name\"."),
                $kind,
                $code,
            );
        }

        return $result;
    }

    /**
     * Grafik organizacji: lista obiektów `resource` (klucz zasobu tej organizacji albo przodka), `performer`
     * (`{"zone": "<strefa>", "email": "<adres>"}`), `weekday` (1–7, ISO), `from` i `to` („HH:MM”, `from` < `to`), `slot`
     * (minuty, 5–240) i opcjonalnie `valid_from`, `valid_to` (RRRR-MM-DD); inne pola to błąd. Istnienie zasobu i konta
     * sprawdza ScheduleInstaller.
     *
     * @return list<InstallSchedule>
     *
     * @throws InvalidInstallFile
     */
    private function schedule(string $path, mixed $entries, string $where): array
    {
        if (! is_array($entries) || ! array_is_list($entries)) {
            throw InvalidInstallFile::in($path, "{$where} must be a list.");
        }

        $result = [];

        foreach ($entries as $index => $entry) {
            $at = "{$where}[{$index}]";

            if (! is_array($entry) || array_is_list($entry)) {
                throw InvalidInstallFile::in($path, "{$at} must be an object.");
            }

            $unknown = array_diff(array_keys($entry), self::SCHEDULE_FIELDS);
            $performer = $entry['performer'] ?? null;
            $weekday = $entry['weekday'] ?? null;
            $slot = $entry['slot'] ?? null;
            $from = $this->values->reference($path, $entry, 'from', $at, self::TIME_PATTERN);
            $to = $this->values->reference($path, $entry, 'to', $at, self::TIME_PATTERN);

            if ($unknown !== []) {
                throw InvalidInstallFile::in($path, "{$at} has unknown fields: " . implode(', ', $unknown) . '.');
            }

            $email = is_array($performer) ? ($performer['email'] ?? null) : null;

            if (! is_array($performer) || ! is_string($performer['zone'] ?? null) || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false || count($performer) !== 2) {
                throw InvalidInstallFile::in($path, "{$at}.performer must be {\"zone\": \"<zone>\", \"email\": \"<address>\"}.");
            }

            if (! is_int($weekday) || $weekday < 1 || $weekday > 7 || ! is_int($slot) || $slot < 5 || $slot > 240 || $from === null || $to === null || $from >= $to) {
                throw InvalidInstallFile::in($path, "{$at} needs \"weekday\" 1–7, \"from\" before \"to\" (HH:MM) and \"slot\" of 5–240 minutes.");
            }

            $result[] = new InstallSchedule(
                $this->values->reference($path, $entry, 'resource', $at, self::KEY_PATTERN) ?? throw InvalidInstallFile::in($path, "{$at} needs a \"resource\"."),
                $performer['zone'],
                mb_strtolower(trim($email)),
                $weekday,
                $from,
                $to,
                $slot,
                $this->values->reference($path, $entry, 'valid_from', $at, self::DATE_PATTERN),
                $this->values->reference($path, $entry, 'valid_to', $at, self::DATE_PATTERN),
                $this->everyWeeks($path, $entry, $at),
                $this->values->flag($path, $entry, 'fills_gaps', $at),
            );
        }

        return $result;
    }

    /**
     * Umowy najmu zasobów organizacji: lista obiektów `resource` (klucz zasobu tej organizacji), `lessee` i `cooperation`
     * (klucze organizacji najemcy i jej podorganizacji z `is_cooperation`), `number` (numer umowy), opcjonalnie `signed`
     * (data zawarcia, RRRR-MM-DD — bez wpływu na grafik), `from` (rozpoczęcie cyklu i okresu umowy) i `to` (RRRR-MM-DD), `windows` (lista `{"days": [1–7], "from": "HH:MM", "to": "HH:MM"}`, bez okien — cały zasób)
     * i `access` (Enums\LeaseAccess: `book`, `view`, `wallboard`); inne pola to błąd. Istnienie organizacji sprawdza
     * InstallOrganizations, zasób i kolizje — LeaseInstaller.
     *
     * @return list<InstallLease>
     *
     * @throws InvalidInstallFile
     */
    private function leases(string $path, mixed $entries, string $where): array
    {
        if (! is_array($entries) || ! array_is_list($entries)) {
            throw InvalidInstallFile::in($path, "{$where} must be a list.");
        }

        $result = [];

        foreach ($entries as $index => $entry) {
            $at = "{$where}[{$index}]";

            if (! is_array($entry) || array_is_list($entry)) {
                throw InvalidInstallFile::in($path, "{$at} must be an object.");
            }

            $unknown = array_diff(array_keys($entry), self::LEASE_FIELDS);

            if ($unknown !== []) {
                throw InvalidInstallFile::in($path, "{$at} has unknown fields: " . implode(', ', $unknown) . '.');
            }

            $from = $this->values->reference($path, $entry, 'from', $at, self::DATE_PATTERN);
            $to = $this->values->reference($path, $entry, 'to', $at, self::DATE_PATTERN);

            if ($from !== null && $to !== null && $to < $from) {
                throw InvalidInstallFile::in($path, "{$at}: \"to\" cannot be before \"from\".");
            }

            $result[] = new InstallLease(
                $this->values->reference($path, $entry, 'resource', $at, self::KEY_PATTERN) ?? throw InvalidInstallFile::in($path, "{$at} needs a \"resource\"."),
                $this->values->reference($path, $entry, 'lessee', $at, self::KEY_PATTERN) ?? throw InvalidInstallFile::in($path, "{$at} needs a \"lessee\"."),
                $this->values->reference($path, $entry, 'cooperation', $at, self::KEY_PATTERN) ?? throw InvalidInstallFile::in($path, "{$at} needs a \"cooperation\"."),
                $this->values->text($path, $entry, 'number', $at, self::NAME_MAX_LENGTH) ?? throw InvalidInstallFile::in($path, "{$at} needs a \"number\"."),
                $from,
                $to,
                $this->windows($path, $entry['windows'] ?? [], "{$at}.windows"),
                array_map(static fn(string $value): LeaseAccess => LeaseAccess::from($value), $this->values->names($path, $entry['access'] ?? [], "{$at}.access", InstallValues::ROLE_PATTERN, LeaseAccess::values())),
                $this->everyWeeks($path, $entry, $at),
                $this->mode($path, $entry, $at),
                $this->values->reference($path, $entry, 'signed', $at, self::DATE_PATTERN),
            );
        }

        return $result;
    }

    /**
     * Tryb umowy (`mode`: Enums\LeaseMode, domyślnie `windows`).
     *
     * @param array<array-key, mixed> $entry
     *
     * @throws InvalidInstallFile
     */
    private function mode(string $path, array $entry, string $where): LeaseMode
    {
        $value = $entry['mode'] ?? LeaseMode::Windows->value;

        return (is_string($value) ? LeaseMode::tryFrom($value) : null) ?? throw InvalidInstallFile::in($path, "{$where}.mode must be one of: " . implode(', ', array_column(LeaseMode::cases(), 'value')) . '.');
    }

    /**
     * Okna umowy najmu: lista `{"days": [1–7], "from": "HH:MM", "to": "HH:MM"}` (`from` przed `to`) rozwinięta do
     * [dzień, od, do].
     *
     * @return list<array{int, string, string}>
     *
     * @throws InvalidInstallFile
     */
    private function windows(string $path, mixed $windows, string $where): array
    {
        if (! is_array($windows) || ! array_is_list($windows)) {
            throw InvalidInstallFile::in($path, "{$where} must be a list.");
        }

        $result = [];

        foreach ($windows as $index => $window) {
            $at = "{$where}[{$index}]";

            if (! is_array($window) || array_is_list($window) || array_diff(array_keys($window), self::WINDOW_FIELDS) !== []) {
                throw InvalidInstallFile::in($path, "{$at} must be {\"days\": [1–7], \"from\": \"HH:MM\", \"to\": \"HH:MM\"}.");
            }

            $days = $window['days'] ?? null;
            $from = $this->values->reference($path, $window, 'from', $at, self::TIME_PATTERN);
            $to = $this->values->reference($path, $window, 'to', $at, self::TIME_PATTERN);

            if (! is_array($days) || $days === [] || ! array_is_list($days) || $from === null || $to === null || $from >= $to) {
                throw InvalidInstallFile::in($path, "{$at} needs \"days\" (list of 1–7) and \"from\" before \"to\" (HH:MM).");
            }

            foreach ($days as $day) {
                if (! is_int($day) || $day < 1 || $day > 7) {
                    throw InvalidInstallFile::in($path, "{$at}.days must list ISO weekdays 1–7.");
                }

                $result[] = [$day, $from, $to];
            }
        }

        return $result;
    }

    /**
     * Cykl tygodni wpisu (`every_weeks`: 1–WeekCycle::MAX_INTERVAL, liczony od tygodnia początku), domyślnie 1.
     *
     * @param array<array-key, mixed> $entry
     *
     * @throws InvalidInstallFile
     */
    private function everyWeeks(string $path, array $entry, string $where): int
    {
        $value = $entry[self::EVERY_WEEKS] ?? 1;

        return is_int($value) && $value >= 1 && $value <= WeekCycle::MAX_INTERVAL ? $value : throw InvalidInstallFile::in($path, "{$where}." . self::EVERY_WEEKS . ' must be 1–' . WeekCycle::MAX_INTERVAL . '.');
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
