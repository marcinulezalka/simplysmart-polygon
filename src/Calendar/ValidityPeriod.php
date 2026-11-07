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

namespace Simplysmart\Polygon\Calendar;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Okres ważności rekordu kalendarza (`valid_from`, `valid_to` — bez końca: null), wspólny dla grafiku i umowy najmu:
 * jedno miejsce reguły „rekord nachodzi na okres”, używanej przez kolizje grafiku i najmu oraz odczyt najmów.
 */
final class ValidityPeriod
{
    public const string FROM = 'valid_from';

    public const string TO = 'valid_to';

    /**
     * Zawęża zapytanie do rekordów, których okres nachodzi na $from–$to (bez końca: null).
     *
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     */
    public static function overlapping(Builder $query, CarbonImmutable $from, ?CarbonImmutable $to = null): void
    {
        $query->where(static function (Builder $period) use ($from): void {
            $period->whereNull(self::TO)->orWhereDate(self::TO, '>=', $from);
        });

        if ($to !== null) {
            $query->whereDate(self::FROM, '<=', $to);
        }
    }
}
