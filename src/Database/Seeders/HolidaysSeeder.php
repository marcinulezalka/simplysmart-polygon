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
use Simplysmart\Polygon\Enums\HolidaySource;
use Simplysmart\Polygon\Models\Dictionaries\Country;
use Simplysmart\Polygon\Models\Dictionaries\Holiday;

/**
 * Dni wolne od pracy krajów: ogólnokrajowe święta Polski na lata 2026–2027 (pobrane z Nager.Date, `source` = nager),
 * jak w bazie skeleton13. Seeder referencyjny, idempotentny po kraju i dniu (także usunięte): brakujący dzień dodaje,
 * a istniejącego (także dnia wolnego wpisanego ręcznie) nie nadpisuje; kraje, których nie ma w słowniku, pomija.
 * `name` w języku kraju, `json_i18n` z nazwą angielską. Kolejne lata dopisuje się tutaj albo synchronizacją z Nager.Date.
 */
final class HolidaysSeeder extends Seeder
{
    /**
     * Kraj => lista [dzień, nazwa w języku kraju, nazwa angielska].
     *
     * @var array<string, list<array{string, string, string}>>
     */
    public const array HOLIDAYS = [
        'PL' => [
            ['2026-01-01', 'Nowy Rok', "New Year's Day"],
            ['2026-01-06', 'Święto Trzech Króli', 'Epiphany'],
            ['2026-04-05', 'Wielkanoc', 'Easter Sunday'],
            ['2026-04-06', 'Drugi Dzień Wielkanocy', 'Easter Monday'],
            ['2026-05-01', 'Święto Pracy', 'May Day'],
            ['2026-05-03', 'Święto Konstytucji 3 Maja', 'Constitution Day'],
            ['2026-05-24', 'Zielone Świątki', 'Pentecost'],
            ['2026-06-04', 'Boże Ciało', 'Corpus Christi'],
            ['2026-08-15', 'Wniebowzięcie Najświętszej Maryi Panny', 'Assumption Day'],
            ['2026-11-01', 'Wszystkich Świętych', "All Saints' Day"],
            ['2026-11-11', 'Narodowe Święto Niepodległości', 'Independence Day'],
            ['2026-12-24', 'Wigilia Bożego Narodzenia', 'Christmas Eve'],
            ['2026-12-25', 'Boże Narodzenie', 'Christmas Day'],
            ['2026-12-26', 'Drugi Dzień Bożego Narodzenia', "St. Stephen's Day"],
            ['2027-01-01', 'Nowy Rok', "New Year's Day"],
            ['2027-01-06', 'Święto Trzech Króli', 'Epiphany'],
            ['2027-03-28', 'Wielkanoc', 'Easter Sunday'],
            ['2027-03-29', 'Drugi Dzień Wielkanocy', 'Easter Monday'],
            ['2027-05-01', 'Święto Pracy', 'May Day'],
            ['2027-05-03', 'Święto Konstytucji 3 Maja', 'Constitution Day'],
            ['2027-05-16', 'Zielone Świątki', 'Pentecost'],
            ['2027-05-27', 'Boże Ciało', 'Corpus Christi'],
            ['2027-08-15', 'Wniebowzięcie Najświętszej Maryi Panny', 'Assumption Day'],
            ['2027-11-01', 'Wszystkich Świętych', "All Saints' Day"],
            ['2027-11-11', 'Narodowe Święto Niepodległości', 'Independence Day'],
            ['2027-12-24', 'Wigilia Bożego Narodzenia', 'Christmas Eve'],
            ['2027-12-25', 'Boże Narodzenie', 'Christmas Day'],
            ['2027-12-26', 'Drugi Dzień Bożego Narodzenia', "St. Stephen's Day"],
        ],
    ];

    private const string ENGLISH = 'en';

    public function __construct(
        private readonly IdSlots $slots,
    ) {}

    public function run(): void
    {
        foreach (self::HOLIDAYS as $alpha2 => $holidays) {
            $country = Country::query()->where(Country::codeColumn(), $alpha2)->first();

            if ($country === null) {
                continue;
            }

            foreach ($holidays as [$date, $name, $english]) {
                if (Holiday::withTrashed()->where('country_id', $country->id)->whereDate('holiday_date', $date)->exists()) {
                    continue;
                }

                (new Holiday())->forceFill([
                    ColumnNames::ID => $this->slots->next(Holiday::TABLE),
                    ColumnNames::NAME => $name,
                    ColumnNames::JSON_I18N => [self::ENGLISH => $english],
                    'country_id' => $country->id,
                    'holiday_date' => $date,
                    'source' => HolidaySource::Nager,
                    'is_day_off' => true,
                    ColumnNames::IS_ACTIVE => true,
                ])->save();
            }
        }
    }
}
