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

namespace Simplysmart\Polygon\Queries\Dictionaries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Simplysmart\Polygon\Database\CodeColumns;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Database\LikePattern;
use Simplysmart\Polygon\Models\Dictionaries\Country;
use Simplysmart\Polygon\Models\Dictionaries\Currency;
use Simplysmart\Polygon\Models\Dictionaries\Holiday;
use Simplysmart\Polygon\Models\Dictionaries\Language;

/**
 * Wyszukiwanie w słownikach (także wyłączonych wpisów) bez rozróżniania wielkości liter: po kodzie, nazwie
 * i tłumaczeniach (`json_i18n`). Języki dodatkowo po nazwie własnej i po krajach, w których są urzędowe, więc
 * „polska” znajduje język polski. Pusty tekst daje pustą listę.
 */
final class DictionarySearch
{
    /**
     * @return Collection<int, Language>
     */
    public function languages(string $term, ?int $limit = null): Collection
    {
        $needle = LikePattern::contains($term);

        if ($needle === null) {
            return new Collection();
        }

        $query = Language::query();
        $query->where(static function (Builder $match) use ($needle): void {
            self::matchEntry($match, Language::CODE, $needle);
            $match->orWhereRaw('LOWER(native_name) LIKE ?', [$needle]);
            $match->orWhereHas('countries', static function (Builder $country) use ($needle): void {
                $country->where(static fn(Builder $countryMatch) => self::matchEntry($countryMatch, CodeColumns::ALPHA_2, $needle));
            });
        });

        return self::ordered($query, Language::codeColumn(), $limit);
    }

    /**
     * @return Collection<int, Country>
     */
    public function countries(string $term, int $limit): Collection
    {
        $needle = LikePattern::contains($term);

        if ($needle === null) {
            return new Collection();
        }

        $query = Country::query();
        $query->where(static function (Builder $match) use ($needle): void {
            self::matchEntry($match, CodeColumns::ALPHA_2, $needle);
            $match->orWhereRaw('LOWER(' . CodeColumns::ALPHA_3 . ') LIKE ?', [$needle]);
        });

        return self::ordered($query, ColumnNames::SORT_ORDER, $limit);
    }

    /**
     * Kraje, które w roku `$year` mają święto o nazwie pasującej do `$term` (nazwa albo tłumaczenia), np. „Boże Narodzenie”.
     *
     * @return Collection<int, Country>
     */
    public function countriesByHoliday(string $term, int $year, int $limit): Collection
    {
        $needle = LikePattern::contains($term);

        if ($needle === null) {
            return new Collection();
        }

        $holidays = Holiday::query();
        $holidays->select('country_id');
        $holidays->whereYear('holiday_date', $year);
        $holidays->where(static function (Builder $match) use ($needle): void {
            $match->whereRaw('LOWER(' . ColumnNames::NAME . ') LIKE ?', [$needle])
                ->orWhereRaw('LOWER(' . ColumnNames::JSON_I18N . ') LIKE ?', [$needle]);
        });

        $query = Country::query();
        $query->whereIn('id', $holidays);

        return self::ordered($query, ColumnNames::SORT_ORDER, $limit);
    }

    /**
     * @return Collection<int, Currency>
     */
    public function currencies(string $term, int $limit): Collection
    {
        $needle = LikePattern::contains($term);

        if ($needle === null) {
            return new Collection();
        }

        $query = Currency::query();
        $query->where(static fn(Builder $match) => self::matchEntry($match, CodeColumns::ALPHA_3, $needle));

        return self::ordered($query, Currency::codeColumn(), $limit);
    }

    /**
     * @param Builder<covariant Model> $query
     * @param literal-string $codeColumn Kolumna kodu słownika (stała z kodu, nigdy z żądania).
     */
    private static function matchEntry(Builder $query, string $codeColumn, string $needle): void
    {
        $query->whereRaw('LOWER(' . $codeColumn . ') LIKE ?', [$needle])
            ->orWhereRaw('LOWER(' . ColumnNames::NAME . ') LIKE ?', [$needle])
            ->orWhereRaw('LOWER(' . ColumnNames::JSON_I18N . ') LIKE ?', [$needle]);
    }

    /**
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     *
     * @return Collection<int, TModel>
     */
    private static function ordered(Builder $query, string $column, ?int $limit): Collection
    {
        $query->orderByDesc(ColumnNames::IS_ACTIVE);
        $query->orderBy($column);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
