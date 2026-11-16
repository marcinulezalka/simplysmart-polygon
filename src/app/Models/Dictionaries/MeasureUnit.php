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

namespace Simplysmart\Polygon\Models\Dictionaries;

use Simplysmart\Polygon\Enums\MeasureUnitKind;
use Simplysmart\Polygon\Models\Concerns\HasI18n;
use Simplysmart\Polygon\Models\DataModel;
use Simplysmart\Polygon\Models\Relations\BelongsToCountry;

/**
 * Jednostka miary kraju: `name` w języku kraju (np. „kilogram”, „pound”), `json_i18n` z nazwami w językach interfejsu,
 * `code` — symbol w dokumentach (`kg`, `szt.`, `lb`), `unece_code` — kod UN/ECE Recommendation 20 (`KGM`, `H87`;
 * null, gdy nie ma pewnego odpowiednika), `kind` — rodzaj (MeasureUnitKind). Unikalne kraj + symbol.
 *
 * @property int $country_id
 * @property string $code
 * @property string|null $unece_code
 * @property MeasureUnitKind $kind
 */
final class MeasureUnit extends DataModel
{
    use BelongsToCountry;
    use HasI18n;

    public const string TABLE = 'locale_measure_units';

    protected $table = self::TABLE;

    public function translationFallback(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'code' => 'string',
            'unece_code' => 'string',
            'kind' => MeasureUnitKind::class,
        ];
    }
}
