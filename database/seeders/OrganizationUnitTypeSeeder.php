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

namespace Simplysmart\Polygon\Database\Seeders;

use Illuminate\Database\Seeder;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Database\IdSlots;
use Simplysmart\Polygon\Models\OrganizationUnitType;

/**
 * Domyślne typy jednostek organizacyjnych w slotach 1–999 (IdSlots::next()): placówka, filia, poradnia, oddział,
 * departament, pracownia, zakład, rejestracja, jednostka ogólna oraz struktura wewnętrzna — dział administracyjny i dział
 * wewnętrzny — z polami podorganizacji (FIELDS). Seeder referencyjny, idempotentny po `code`: brakujący typ dodaje, a typu
 * zmienionego przez administratora nie nadpisuje.
 */
final class OrganizationUnitTypeSeeder extends Seeder
{
    /**
     * Typy domyślne w kolejności listy: kod => [nazwa, prefiks numeru, ikona, struktura wewnętrzna].
     *
     * @var array<string, array{string, string, string, bool}>
     */
    public const array DEFAULTS = [
        'placowka' => ['Placówka', 'PLA-', 'building', false],
        'filia' => ['Filia', 'FIL-', 'home', false],
        'poradnia' => ['Poradnia', 'POR-', 'cross', false],
        'oddzial' => ['Oddział', 'ODD-', 'activity', false],
        'departament' => ['Departament', 'DEP-', 'grid', false],
        'pracownia' => ['Pracownia', 'PRA-', 'cpu', false],
        'zaklad' => ['Zakład', 'ZAK-', 'clipboard', false],
        'administracja' => ['Dział administracyjny', 'ADM-', 'users', true],
        'dzial' => ['Dział wewnętrzny', 'DZW-', 'settings', true],
        'rejestracja' => ['Rejestracja', 'REJ-', 'book-open', false],
        'ogolna' => ['Jednostka ogólna', 'JED-', 'grid', false],
    ];

    /**
     * Pola uzupełniane przy zakładaniu podorganizacji typu (`json_attributes.fields`, OrganizationUnitType::fields()).
     *
     * @var array<string, list<string>>
     */
    public const array FIELDS = [
        'placowka' => ['Kod resortowy', 'Godziny otwarcia'],
        'filia' => ['Godziny otwarcia'],
        'poradnia' => ['Specjalność', 'Kod resortowy (część VIII)', 'Godziny przyjęć'],
        'oddzial' => ['Specjalność', 'Liczba łóżek'],
        'departament' => ['Kierownik'],
        'pracownia' => ['Zakres badań'],
        'zaklad' => ['Kierownik'],
        'administracja' => ['Kierownik', 'Lokalizacja'],
        'dzial' => ['Kierownik', 'Lokalizacja'],
        'rejestracja' => ['Telefon rejestracji', 'Godziny pracy', 'Lokalizacja'],
        'ogolna' => ['Opis'],
    ];

    public function __construct(
        private readonly IdSlots $slots,
    ) {}

    public function run(): void
    {
        $order = 0;

        foreach (self::DEFAULTS as $code => [$name, $prefix, $icon, $internal]) {
            $order += 10;

            if (OrganizationUnitType::withTrashed()->where('code', $code)->exists()) {
                continue;
            }

            (new OrganizationUnitType())->forceFill([
                ColumnNames::ID => $this->slots->next(OrganizationUnitType::TABLE),
                ColumnNames::NAME => $name,
                'code' => $code,
                'number_prefix' => $prefix,
                'icon' => $icon,
                'is_internal' => $internal,
                'is_public' => ! $internal,
                ColumnNames::IS_ACTIVE => true,
                ColumnNames::SORT_ORDER => $order,
                ColumnNames::JSON_ATTRIBUTES => [OrganizationUnitType::FIELDS => self::FIELDS[$code]],
            ])->save();
        }
    }
}
