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

use Simplysmart\Polygon\Auth\Concerns\SignsInWithCodeAndPin;
use Simplysmart\Polygon\Contracts\CodeAndPinAccount;
use Simplysmart\Polygon\Models\Relations\HasOrganizations;
use Spatie\Permission\Traits\HasRoles;

/**
 * Baza modelu kont strefy logującej się kodem z e-maila i PIN-em (tabela `<strefa>_accounts` z Database\ZoneTables),
 * z rolami i uprawnieniami spatie w guardzie strefy i członkostwem w organizacjach (organizations()). Model strefy ustawia tabelę i guard ról:
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
}
