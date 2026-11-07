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

namespace Simplysmart\Polygon\Auth;

use Illuminate\Database\ConnectionInterface;
use Simplysmart\Polygon\Database\ColumnNames;
use Simplysmart\Polygon\Models\ZoneAccount;
use Simplysmart\Polygon\Organizations\OrganizationMembership;
use Simplysmart\Polygon\Permissions\OrganizationRoles;
use Simplysmart\Polygon\Queries\Accounts\ZoneAccounts;
use Simplysmart\Polygon\Zones\ZoneDefinition;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

/**
 * Zakłada aktywne konto strefy bez PIN-u (PIN ustawia właściciel przy pierwszym logowaniu), z globalnymi rolami spatie
 * guarda strefy (zespół null, Permissions\OrganizationRoles; role w organizacjach nadaje instalator), w jednej transakcji: brak roli wycofuje całe założenie. Konto z rolą hero zostaje członkiem organizacji hero
 * (Organizations\OrganizationMembership::joinHeroOrganization()). Dla stref bez rejestracji — wywoływane z komendy
 * `<strefa>:account:create` (Console\CreateZoneAccountCommand) i z instalatora (konta startowe).
 */
final readonly class CreateZoneAccount
{
    public function __construct(
        private ConnectionInterface $db,
        private ZoneAccounts $accounts,
        private OrganizationMembership $membership,
        private OrganizationRoles $roles,
    ) {}

    /**
     * @param list<string> $roles Nazwy istniejących ról guarda strefy (globalne).
     * @param int|null $id Stałe id konta z puli seederów (IdSlots, 1–999), np. konto startowe z pliku instalacji;
     *                     null — kolejne id (od 1000).
     *
     * @throws AccountAlreadyExists Gdy strefa ma już konto z tym adresem (także usunięte).
     * @throws RoleDoesNotExist Gdy którejś roli nie ma; konto nie powstaje.
     */
    public function handle(ZoneDefinition $zone, string $name, string $email, array $roles = [], ?int $id = null): ZoneAccount
    {
        if ($this->accounts->findByEmail($zone, $email) !== null) {
            throw AccountAlreadyExists::inZone($zone->name);
        }

        $model = $zone->accountModel();

        return $this->db->transaction(function () use ($model, $name, $email, $roles, $id): ZoneAccount {
            $account = new $model();
            $account->forceFill([
                ColumnNames::ID => $id,
                AccountColumns::NAME => $name,
                AccountColumns::EMAIL => $email,
                AccountColumns::IS_ACTIVE => true,
            ])->save();

            if ($roles !== []) {
                $this->roles->assign($account, $roles, null);
                $this->membership->joinHeroOrganization($account, $roles);
            }

            return $account;
        });
    }
}
