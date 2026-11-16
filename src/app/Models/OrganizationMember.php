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

use Illuminate\Database\Eloquent\Model;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Relations\BelongsToMember;
use Simplysmart\Polygon\Models\Relations\BelongsToOrganization;

/**
 * Członkostwo konta strefy w organizacji (tabela łącząca `organization_members`, bez kolumn standardowych,
 * z historią). Niezależne od ról.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $model_type
 * @property int $model_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class OrganizationMember extends Model
{
    use BelongsToMember;
    use BelongsToOrganization;

    public const string TABLE = 'organization_members';

    /** Nazwa morph członka (kolumny `model_type`, `model_id`). */
    public const string MEMBER_MORPH = 'model';

    public const string MEMBER_TYPE = 'model_type';

    public const string MEMBER_ID = 'model_id';

    protected $table = self::TABLE;

    /**
     * Kolumny członka dla konta strefy (morph `model`): warunek zapytań i wartości nowego członkostwa.
     *
     * @return array{model_type: string, model_id: mixed}
     */
    public static function keyOf(Model $account): array
    {
        return [self::MEMBER_TYPE => $account->getMorphClass(), self::MEMBER_ID => $account->getKey()];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ColumnNames::ID => 'integer',
            Organization::FOREIGN_KEY => 'integer',
            self::MEMBER_TYPE => 'string',
            self::MEMBER_ID => 'integer',
            ColumnNames::CREATED_AT => 'datetime',
            ColumnNames::UPDATED_AT => 'datetime',
        ];
    }
}
