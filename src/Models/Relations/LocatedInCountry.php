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

namespace Simplysmart\Polygon\Models\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Simplysmart\Polygon\Database\CodeColumns;
use Simplysmart\Polygon\Models\Dictionaries\Country;

/**
 * Kraj adresu rekordu po kodzie alpha-2 (kolumna `country_code` → `locale_countries.alpha_code_2`, opcjonalna).
 */
trait LocatedInCountry
{
    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', CodeColumns::ALPHA_2);
    }
}
