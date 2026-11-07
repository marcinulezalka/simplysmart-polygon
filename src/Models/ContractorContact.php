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

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Osoba kontaktowa kontrahenta (`contractor_contacts`): `name` (imię i nazwisko), `role`, `phone`, `email`
 * i `is_primary` — jeden kontakt główny na kontrahenta (Contractors\SaveContact).
 *
 * @property int $contractor_id
 * @property string|null $role
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_primary
 * @property-read Contractor|null $contractor
 */
final class ContractorContact extends DataModel
{
    public const string TABLE = 'contractor_contacts';

    public const string ROLE = 'role';

    public const string PHONE = 'phone';

    public const string EMAIL = 'email';

    public const string IS_PRIMARY = 'is_primary';

    protected $table = self::TABLE;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [self::IS_PRIMARY => false, 'is_active' => true];

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, Contractor::FOREIGN_KEY);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [Contractor::FOREIGN_KEY => 'integer', self::ROLE => 'string', self::PHONE => 'string', self::EMAIL => 'string', self::IS_PRIMARY => 'boolean'];
    }
}
