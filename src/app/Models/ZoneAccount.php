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

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Simplysmart\Polygon\Auth\Concerns\SignsInWithCodeAndPin;
use Simplysmart\Polygon\Contracts\CodeAndPinAccount;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\Relations\HasOrganizations;
use Simplysmart\Polygon\Permissions\ActiveRole;
use Spatie\Permission\Traits\HasRoles;

/**
 * Baza modelu kont strefy logującej się kodem z e-maila i PIN-em (tabela `<strefa>_accounts` z Database\ZoneTables),
 * z rolami i uprawnieniami spatie w guardzie strefy i członkostwem w organizacjach (organizations()). W strefie
 * z kontekstem roli (`role_contexts`) wczytane role (setRelation()) obejmują w bieżącej organizacji tylko aktywną rolę konta
 * (Permissions\ActiveRole). Model strefy ustawia tabelę i guard ról:
 *
 *     final class ShopAccount extends ZoneAccount
 *     {
 *         protected $table = 'shop_accounts';
 *
 *         protected string $guard_name = 'shop';
 *     }
 *
 * @property int $id
 * @property string $token
 * @property string $name
 * @property string $email
 * @property string $email_hash
 * @property string|null $pin_hash
 * @property bool $is_active
 * @property string|null $checksum
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
abstract class ZoneAccount extends MainAuthenticatable implements CodeAndPinAccount
{
    use HasOrganizations;
    use HasRoles;
    use SignsInWithCodeAndPin;

    /** Relacja ról spatie (HasRoles::roles()). */
    private const string ROLES_RELATION = 'roles';

    /**
     * Ustawia relację modelu; wczytane role (leniwie albo przez load()/loadMissing(), z których korzysta spatie)
     * obejmują, gdy konto pracuje w aktywnej roli bieżącego zespołu (Permissions\ActiveRole), tylko ją — więc
     * uprawnienia, hasRole() i getRoleNames() liczą jedną rolę. Zapytanie roles() zostaje pełne; role nadaje
     * się przez Permissions\OrganizationRoles::assign(), które wczytuje je bez zawężenia.
     *
     * @param string $relation
     * @param mixed $value
     *
     * @return $this
     */
    public function setRelation($relation, $value)
    {
        if ($relation === self::ROLES_RELATION && $value instanceof Collection) {
            $role = Container::getInstance()->make(ActiveRole::class)->of($this);
            $value = $role === null ? $value : $value->filter(static fn(Model $model): bool => $model->getAttribute(ColumnNames::NAME) === $role)->values();
        }

        return parent::setRelation($relation, $value);
    }
}
