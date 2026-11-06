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
use Simplysmart\Polygon\Enums\MeasureUnitKind;
use Simplysmart\Polygon\Models\Dictionaries\Country;
use Simplysmart\Polygon\Models\Dictionaries\MeasureUnit;

/**
 * Jednostki podstawowe Polski (metryczne, z sztuką, opakowaniem, kompletem i usługą), Wielkiej Brytanii (metryczne
 * z imperialnymi: mila, pinta, galon, stopa, cal, funt, kamień) i USA (amerykańskie: funt, uncja, galon, stopa, akr,
 * tuzin). Seeder referencyjny, idempotentny po kraju i symbolu (także usunięte): brakującą jednostkę dodaje, a zmienionej
 * ręcznie nie nadpisuje; kraje, których nie ma w słowniku, pomija. `name` w języku kraju, `json_i18n` po polsku
 * i angielsku. Kody UN/ECE Recommendation 20 tylko tam, gdzie odpowiednik jest pewny (inaczej null).
 */
final class MeasureUnitsSeeder extends Seeder
{
    /**
     * Kraj => lista [symbol, kod UN/ECE, rodzaj, nazwa po polsku, nazwa po angielsku]; nazwa w języku kraju to
     * polska dla PL, angielska dla GB i US.
     *
     * @var array<string, list<array{string, ?string, MeasureUnitKind, string, string}>>
     */
    public const array UNITS = [
        'PL' => [
            ['szt.', 'H87', MeasureUnitKind::Count, 'sztuka', 'piece'],
            ['kpl.', 'SET', MeasureUnitKind::Count, 'komplet', 'set'],
            ['para', 'PR', MeasureUnitKind::Count, 'para', 'pair'],
            ['op.', 'XPK', MeasureUnitKind::Packaging, 'opakowanie', 'package'],
            ['kg', 'KGM', MeasureUnitKind::Mass, 'kilogram', 'kilogram'],
            ['g', 'GRM', MeasureUnitKind::Mass, 'gram', 'gram'],
            ['t', 'TNE', MeasureUnitKind::Mass, 'tona', 'tonne'],
            ['m', 'MTR', MeasureUnitKind::Length, 'metr', 'metre'],
            ['cm', 'CMT', MeasureUnitKind::Length, 'centymetr', 'centimetre'],
            ['mm', 'MMT', MeasureUnitKind::Length, 'milimetr', 'millimetre'],
            ['km', 'KMT', MeasureUnitKind::Length, 'kilometr', 'kilometre'],
            ['m²', 'MTK', MeasureUnitKind::Area, 'metr kwadratowy', 'square metre'],
            ['m³', 'MTQ', MeasureUnitKind::Volume, 'metr sześcienny', 'cubic metre'],
            ['l', 'LTR', MeasureUnitKind::Volume, 'litr', 'litre'],
            ['ml', 'MLT', MeasureUnitKind::Volume, 'mililitr', 'millilitre'],
            ['h', 'HUR', MeasureUnitKind::Time, 'godzina', 'hour'],
            ['min', 'MIN', MeasureUnitKind::Time, 'minuta', 'minute'],
            ['dzień', 'DAY', MeasureUnitKind::Time, 'dzień', 'day'],
            ['mies.', 'MON', MeasureUnitKind::Time, 'miesiąc', 'month'],
            ['kWh', 'KWH', MeasureUnitKind::Energy, 'kilowatogodzina', 'kilowatt-hour'],
            ['usł.', 'E48', MeasureUnitKind::Service, 'usługa', 'service'],
        ],
        'GB' => [
            ['pc', 'H87', MeasureUnitKind::Count, 'sztuka', 'piece'],
            ['set', 'SET', MeasureUnitKind::Count, 'komplet', 'set'],
            ['pk', 'XPK', MeasureUnitKind::Packaging, 'opakowanie', 'pack'],
            ['kg', 'KGM', MeasureUnitKind::Mass, 'kilogram', 'kilogram'],
            ['g', 'GRM', MeasureUnitKind::Mass, 'gram', 'gram'],
            ['t', 'TNE', MeasureUnitKind::Mass, 'tona', 'tonne'],
            ['lb', 'LBR', MeasureUnitKind::Mass, 'funt', 'pound'],
            ['oz', 'ONZ', MeasureUnitKind::Mass, 'uncja', 'ounce'],
            ['st', 'STI', MeasureUnitKind::Mass, 'kamień', 'stone'],
            ['m', 'MTR', MeasureUnitKind::Length, 'metr', 'metre'],
            ['cm', 'CMT', MeasureUnitKind::Length, 'centymetr', 'centimetre'],
            ['km', 'KMT', MeasureUnitKind::Length, 'kilometr', 'kilometre'],
            ['in', 'INH', MeasureUnitKind::Length, 'cal', 'inch'],
            ['ft', 'FOT', MeasureUnitKind::Length, 'stopa', 'foot'],
            ['yd', 'YRD', MeasureUnitKind::Length, 'jard', 'yard'],
            ['mi', 'SMI', MeasureUnitKind::Length, 'mila', 'mile'],
            ['m²', 'MTK', MeasureUnitKind::Area, 'metr kwadratowy', 'square metre'],
            ['m³', 'MTQ', MeasureUnitKind::Volume, 'metr sześcienny', 'cubic metre'],
            ['l', 'LTR', MeasureUnitKind::Volume, 'litr', 'litre'],
            ['ml', 'MLT', MeasureUnitKind::Volume, 'mililitr', 'millilitre'],
            ['pt', 'PTI', MeasureUnitKind::Volume, 'pinta brytyjska', 'pint'],
            ['gal', 'GLI', MeasureUnitKind::Volume, 'galon brytyjski', 'gallon'],
            ['h', 'HUR', MeasureUnitKind::Time, 'godzina', 'hour'],
            ['day', 'DAY', MeasureUnitKind::Time, 'dzień', 'day'],
            ['kWh', 'KWH', MeasureUnitKind::Energy, 'kilowatogodzina', 'kilowatt-hour'],
        ],
        'US' => [
            ['ea', 'EA', MeasureUnitKind::Count, 'sztuka', 'each'],
            ['doz', 'DZN', MeasureUnitKind::Count, 'tuzin', 'dozen'],
            ['set', 'SET', MeasureUnitKind::Count, 'komplet', 'set'],
            ['pk', 'XPK', MeasureUnitKind::Packaging, 'opakowanie', 'pack'],
            ['lb', 'LBR', MeasureUnitKind::Mass, 'funt', 'pound'],
            ['oz', 'ONZ', MeasureUnitKind::Mass, 'uncja', 'ounce'],
            ['ton', 'STN', MeasureUnitKind::Mass, 'tona amerykańska', 'short ton'],
            ['in', 'INH', MeasureUnitKind::Length, 'cal', 'inch'],
            ['ft', 'FOT', MeasureUnitKind::Length, 'stopa', 'foot'],
            ['yd', 'YRD', MeasureUnitKind::Length, 'jard', 'yard'],
            ['mi', 'SMI', MeasureUnitKind::Length, 'mila', 'mile'],
            ['sq ft', 'FTK', MeasureUnitKind::Area, 'stopa kwadratowa', 'square foot'],
            ['ac', 'ACR', MeasureUnitKind::Area, 'akr', 'acre'],
            ['cu ft', 'FTQ', MeasureUnitKind::Volume, 'stopa sześcienna', 'cubic foot'],
            ['fl oz', 'OZA', MeasureUnitKind::Volume, 'uncja płynu', 'fluid ounce'],
            ['pt', null, MeasureUnitKind::Volume, 'pinta amerykańska', 'pint'],
            ['qt', null, MeasureUnitKind::Volume, 'kwarta', 'quart'],
            ['gal', 'GLL', MeasureUnitKind::Volume, 'galon amerykański', 'gallon'],
            ['hr', 'HUR', MeasureUnitKind::Time, 'godzina', 'hour'],
            ['day', 'DAY', MeasureUnitKind::Time, 'dzień', 'day'],
            ['kWh', 'KWH', MeasureUnitKind::Energy, 'kilowatogodzina', 'kilowatt-hour'],
        ],
    ];

    public function __construct(
        private readonly IdSlots $slots,
    ) {}

    public function run(): void
    {
        foreach (self::UNITS as $alpha2 => $units) {
            $country = Country::query()->where(Country::codeColumn(), $alpha2)->first();

            if ($country === null) {
                continue;
            }

            $order = 0;

            foreach ($units as [$code, $unece, $kind, $polish, $english]) {
                $order += 10;

                if (MeasureUnit::withTrashed()->where('country_id', $country->id)->where('code', $code)->exists()) {
                    continue;
                }

                (new MeasureUnit())->forceFill([
                    ColumnNames::ID => $this->slots->next(MeasureUnit::TABLE),
                    ColumnNames::NAME => $alpha2 === 'PL' ? $polish : $english,
                    ColumnNames::JSON_I18N => ['pl' => $polish, 'en' => $english],
                    'country_id' => $country->id,
                    'code' => $code,
                    'unece_code' => $unece,
                    'kind' => $kind,
                    ColumnNames::IS_ACTIVE => true,
                    ColumnNames::SORT_ORDER => $order,
                ])->save();
            }
        }
    }
}
