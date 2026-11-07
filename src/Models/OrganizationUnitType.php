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

namespace Simplysmart\Polygon\Models;

/**
 * Typ jednostki organizacyjnej ze słownika `organization_unit_types` (np. placówka, filia, poradnia, oddział,
 * departament, dział administracyjny). `code` — stały klucz typu; `number_prefix` — prefiks numeru jednostki
 * w widoku (np. `POR-`); `icon` — ikona z Organization::ICONS dla jednostek bez własnej; `is_internal` — struktura
 * wewnętrzna (np. księgowość, serwis); `is_public` — domyślna widoczność nowej jednostki tego typu w publicznym
 * grafiku; `json_attributes.fields` — nazwy pól, które uzupełnia się przy zakładaniu podorganizacji tego typu
 * (fields()). Jednostka wskazuje typ kolumną Organization::UNIT_TYPE_ID.
 *
 * @property string $code
 * @property string $number_prefix
 * @property string $icon
 * @property bool $is_internal
 * @property bool $is_public
 */
final class OrganizationUnitType extends DataModel
{
    public const string TABLE = 'organization_unit_types';

    /** Stały klucz typu (kolumna `code`, np. `placowka`). */
    public const string CODE = 'code';

    /** Najdłuższy prefiks numeru (kolumna `number_prefix`). */
    public const int PREFIX_MAX_LENGTH = 8;

    /** Klucz listy pól typu w `json_attributes`. */
    public const string FIELDS = 'fields';

    protected $table = self::TABLE;

    /**
     * Wartości domyślne kolumn jak w migracji.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['number_prefix' => '', 'icon' => Organization::ICONS[0], 'is_internal' => false, 'is_public' => true];

    /**
     * Ikona typu z Organization::ICONS (spoza listy — pierwsza ikona).
     */
    public function iconName(): string
    {
        return in_array($this->icon, Organization::ICONS, true) ? $this->icon : Organization::ICONS[0];
    }

    /**
     * Nazwy pól uzupełnianych przy zakładaniu podorganizacji tego typu (`json_attributes.fields`), bez pustych i powtórzeń.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        $fields = ($this->json_attributes ?? [])[self::FIELDS] ?? [];

        return is_array($fields) ? array_values(array_unique(array_filter(array_map(static fn(mixed $field): string => is_string($field) ? trim($field) : '', $fields), static fn(string $field): bool => $field !== ''))) : [];
    }

    /**
     * Numer jednostki w widoku: prefiks typu i numer (np. `POR-1160`); bez numeru null.
     */
    public function displayNumber(?string $number): ?string
    {
        return $number === null || $number === '' ? null : $this->number_prefix . $number;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [self::CODE => 'string', 'number_prefix' => 'string', 'icon' => 'string', 'is_internal' => 'boolean', 'is_public' => 'boolean'];
    }
}
