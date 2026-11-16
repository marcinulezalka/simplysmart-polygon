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

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Simplysmart\Polygon\Enums\ContractStatus;

/**
 * Umowa z kontrahentem (`contractor_contracts`): `name` — numer umowy, `signed_on`, okres `valid_from`–`valid_to`
 * (bez końca: null), `notes`; stan w dniu liczy status() (Enums\ContractStatus).
 *
 * @property int $contractor_id
 * @property CarbonImmutable|null $signed_on
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_to
 * @property string|null $notes
 * @property-read Contractor|null $contractor
 */
final class ContractorContract extends DataModel
{
    public const string TABLE = 'contractor_contracts';

    public const string SIGNED_ON = 'signed_on';

    public const string VALID_FROM = 'valid_from';

    public const string VALID_TO = 'valid_to';

    public const string NOTES = 'notes';

    protected $table = self::TABLE;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, Contractor::FOREIGN_KEY);
    }

    /**
     * Stan umowy w dniu (domyślnie dziś).
     */
    public function status(?CarbonImmutable $day = null): ContractStatus
    {
        return ContractStatus::of($this->valid_from, $this->valid_to, $day ?? CarbonImmutable::today());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [Contractor::FOREIGN_KEY => 'integer', self::SIGNED_ON => 'immutable_date', self::VALID_FROM => 'immutable_date', self::VALID_TO => 'immutable_date', self::NOTES => 'string'];
    }
}
