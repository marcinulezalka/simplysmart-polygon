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
use Simplysmart\Polygon\Database\CodeColumns;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Database\IdSlots;
use Simplysmart\Polygon\Enums\ExchangeRateSource;
use Simplysmart\Polygon\Models\Dictionaries\Currency;
use Simplysmart\Polygon\Models\Dictionaries\ExchangeRate;

/**
 * Kursy walut w złotych (PLN za 1 jednostkę) z tabeli A NBP: CHF, EUR, GBP i USD z dni publikacji 2026-10-29 –
 * 2026-11-05, jak w bazie skeleton13 (`source` = nbp, numer tabeli). Seeder referencyjny, idempotentny po walucie i dniu
 * (także usunięte): brakujący kurs dodaje, a istniejącego (także ręcznego) nie nadpisuje; waluty, których nie ma
 * w słowniku, pomija. Kolejne dni dopisuje się tutaj albo synchronizacją z NBP.
 */
final class ExchangeRatesSeeder extends Seeder
{
    /**
     * Lista [dzień publikacji, waluta ISO 4217, kurs, numer tabeli NBP].
     *
     * @var list<array{string, string, numeric-string, string}>
     */
    public const array RATES = [
        ['2026-10-29', 'CHF', '4.762800', '211/A/NBP/2026'],
        ['2026-10-29', 'EUR', '4.395100', '211/A/NBP/2026'],
        ['2026-10-29', 'GBP', '5.254500', '211/A/NBP/2026'],
        ['2026-10-29', 'USD', '3.947300', '211/A/NBP/2026'],
        ['2026-10-30', 'CHF', '4.779500', '212/A/NBP/2026'],
        ['2026-10-30', 'EUR', '4.395500', '212/A/NBP/2026'],
        ['2026-10-30', 'GBP', '5.270100', '212/A/NBP/2026'],
        ['2026-10-30', 'USD', '3.925100', '212/A/NBP/2026'],
        ['2026-11-02', 'CHF', '4.798300', '213/A/NBP/2026'],
        ['2026-11-02', 'EUR', '4.418500', '213/A/NBP/2026'],
        ['2026-11-02', 'GBP', '5.286900', '213/A/NBP/2026'],
        ['2026-11-02', 'USD', '3.938900', '213/A/NBP/2026'],
        ['2026-11-03', 'CHF', '4.832400', '214/A/NBP/2026'],
        ['2026-11-03', 'EUR', '4.423300', '214/A/NBP/2026'],
        ['2026-11-03', 'GBP', '5.303700', '214/A/NBP/2026'],
        ['2026-11-03', 'USD', '3.952600', '214/A/NBP/2026'],
        ['2026-11-04', 'CHF', '4.788300', '215/A/NBP/2026'],
        ['2026-11-04', 'EUR', '4.401400', '215/A/NBP/2026'],
        ['2026-11-04', 'GBP', '5.273700', '215/A/NBP/2026'],
        ['2026-11-04', 'USD', '3.909700', '215/A/NBP/2026'],
        ['2026-11-05', 'CHF', '4.784400', '216/A/NBP/2026'],
        ['2026-11-05', 'EUR', '4.391600', '216/A/NBP/2026'],
        ['2026-11-05', 'GBP', '5.264400', '216/A/NBP/2026'],
        ['2026-11-05', 'USD', '3.910100', '216/A/NBP/2026'],
    ];

    public function __construct(
        private readonly IdSlots $slots,
    ) {}

    public function run(): void
    {
        $currencies = Currency::query()->get()->keyBy(CodeColumns::ALPHA_3);

        foreach (self::RATES as [$date, $code, $rate, $table]) {
            $currency = $currencies->get($code);

            if (! $currency instanceof Currency || ExchangeRate::withTrashed()->where('currency_id', $currency->id)->whereDate('rate_date', $date)->exists()) {
                continue;
            }

            (new ExchangeRate())->forceFill([
                ColumnNames::ID => $this->slots->next(ExchangeRate::TABLE),
                ColumnNames::NAME => $code . ' ' . $date,
                'currency_id' => $currency->id,
                'rate_date' => $date,
                'rate' => $rate,
                'source' => ExchangeRateSource::Nbp,
                'table_number' => $table,
                ColumnNames::IS_ACTIVE => true,
            ])->save();
        }
    }
}
